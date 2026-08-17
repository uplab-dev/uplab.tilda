<?php

/*
 * Интеграция с Tilda (uplab.tilda) — модуль для CMS 1С-Битрикс
 * Copyright (C) 2025  ООО «Аплэб»
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Uplab\Tilda\Common;
use Uplab\Tilda\Enum\MoveResourcesTarget;
use Uplab\Tilda\Service\Cache;
use Bitrix\Main\Localization\Loc;

const STOP_STATISTICS = true;
const NO_AGENT_CHECK = true;
const DisableEventsCheck = true;
const BX_SECURITY_SHOW_MESSAGE = true;
const PUBLIC_AJAX_MODE = true;
const NOT_CHECK_PERMISSIONS = true;
const ADMIN_MODULE_NAME = 'uplab.tilda';

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

global $APPLICATION, $USER;

if (!Loader::includeModule(ADMIN_MODULE_NAME)) {
    \CMain::FinalActions(Loc::getMessage('ACCESS_DENIED'));
}

if (!$USER->IsAuthorized() || $APPLICATION->GetGroupRight(ADMIN_MODULE_NAME) < "R") {
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}

if (!check_bitrix_sessid()) {
    ShowError(Loc::getMessage('uplab.tilda_SESSION_EXPIRED'));
    CMain::FinalActions();
}

$request = Context::getCurrent()->getRequest();

// Pre-fill values when editing an existing tag (passed by visual.js as EDIT_* params).
$editProject = (int)$request->get('EDIT_PROJECT');
$editPage = (int)$request->get('EDIT_PAGE');
$editHideTemplate = ($request->get('EDIT_HIDEPAGETEMPLATE') === 'Y');
$editMoveResources = MoveResourcesTarget::fromMixed($request->get('EDIT_MOVERESOURCESTO'));

// Значения формы, пришедшие при сохранении.
$submittedProject = (int)$request->get('PROJECT');
$submittedPage = (int)$request->get('PAGE');
$submitError = '';

// Сохранение: тег вставляется только с конкретной страницей. Пустой список
// страниц (в проекте их нет или Tilda не ответила) не должен превращаться
// в заведомо нерабочий тег с PAGE=0.
if ($submittedProject > 0) {
    if ($submittedPage > 0) {
        $tagStr = "UPLABTILDA PROJECT={$submittedProject} PAGE={$submittedPage}";

        if ($request->get('no_template') === 'Y') {
            $tagStr .= ' HIDEPAGETEMPLATE=Y';
        }

        $moveTarget = MoveResourcesTarget::fromMixed($request->get('move_resources_to'));
        if (MoveResourcesTarget::shouldMove($moveTarget)) {
            $tagStr .= ' MOVERESOURCESTO=' . $moveTarget;
        }
        ?>
        <script>
            <?php // Close the window ?>
            BX.WindowManager.Get().AllowClose();
            BX.WindowManager.Get().Close();
            <?php // Insert a line into the visual editor ?>
            window.tildaTag('[' + '<?= CUtil::JSEscape($tagStr) ?>' + ']');
        </script>
        <?php
        require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin_after.php");

        return;
    }

    $submitError = Loc::getMessage('uplab.tilda_PAGE_NOT_SELECTED');
}

// Build the resource-move dropdown options from the single source of truth (enum).
$moveResourcesOptions = array();
foreach (MoveResourcesTarget::langSuffixes() as $optValue => $langSuffix) {
    $moveResourcesOptions[$optValue] = Loc::getMessage('uplab.tilda_' . $langSuffix);
}

// Receive a list of projects
$arProjects = Common::getAssocProjectsList();
// Пустой список сам по себе не говорит, что проектов нет: запрос мог не
// удаться. Ошибку последнего обращения к API отдаёт кэш-сервис.
$projectsError = Cache::getLastError();

if ($projectsError !== null) {
    \CMain::FinalActions(
        htmlspecialcharsbx(
            Loc::getMessage('uplab.tilda_PROJECTS_LOAD_ERROR', ['#MESSAGE#' => $projectsError])
        )
    );
}

if (empty($arProjects)) {
    \CMain::FinalActions(GetMessage("uplab.tilda_NO_PROJECTS") . " <a href=\"/bitrix/admin/settings.php?lang=" . LANGUAGE_ID . "&mid=uplab.tilda\">" . GetMessage("uplab.tilda_NO_KEYS") . "</a>");
}

// Проект, для которого показывается список страниц: редактируемый, отправленный
// формой либо первый в списке. Страницы остальных проектов не запрашиваются —
// раньше попап тянул их для всех проектов сразу и открывался тем дольше, чем
// больше проектов в аккаунте.
$selectedProject = 0;
foreach ([$submittedProject, $editProject] as $candidate) {
    if ($candidate > 0 && isset($arProjects[$candidate])) {
        $selectedProject = $candidate;
        break;
    }
}

if ($selectedProject === 0) {
    $selectedProject = (int)array_key_first($arProjects);
}

$pagesResult = Common::getAssocPagesResult($selectedProject);
$arPages = $pagesResult['pages'];
$pagesError = $pagesResult['error'];

$jsMessages = [
    'loading' => Loc::getMessage('uplab.tilda_PAGES_LOADING'),
    'empty'   => Loc::getMessage('uplab.tilda_PAGES_EMPTY'),
    'error'   => Loc::getMessage('uplab.tilda_PAGES_LOAD_ERROR'),
    'timeout' => Loc::getMessage('uplab.tilda_PAGES_LOAD_TIMEOUT'),
    'retry'   => Loc::getMessage('uplab.tilda_RETRY'),
];

// Список для клиентского кэша: те же данные, что отрисованы в селекте.
$jsPages = [];
foreach ($arPages as $id => $title) {
    $jsPages[] = ['id' => (int)$id, 'title' => (string)$title];
}

/**
 * Кодирует данные для вставки в тело <script>.
 *
 * Заголовки страниц приходят из Tilda и могут содержать угловые скобки:
 * последовательность «</script>» в заголовке закрыла бы тег и превратила
 * остаток данных в разметку. JSON_HEX_TAG экранирует их в < / >.
 *
 * @param mixed $value
 * @return string
 */
$jsonForScript = function ($value) {
    return json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
};

// Draw the form
?>
    <form id="page_select_form">
        <?php
        // CSRF token, validated on submit via check_bitrix_sessid() ?>
        <?php
        echo bitrix_sessid_post(); ?>
        <table class="bxcompprop-content-table">
            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-title" colspan="2"><?= Loc::getMessage("uplab.tilda_PAGE_SELECT"); ?></td>
            </tr>
            <?php
            if ($submitError !== '') { ?>
                <tr class="bxcompprop-prop-tr">
                    <td class="bxcompprop-cont-table-r" colspan="2">
                        <span style="color: #c00;"><?= htmlspecialcharsbx($submitError) ?></span>
                    </td>
                </tr>
                <?php
            } ?>
            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for="projects_select"><?= Loc::getMessage("uplab.tilda_SELECT_PROJECT"); ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <select size="1" name="PROJECT" id="projects_select">
                        <?php
                        foreach ($arProjects as $key => $value) { ?>
                            <option value="<?= htmlspecialcharsbx($key) ?>" <?= ((int)$key === $selectedProject) ? "selected" : "" ?>><?= htmlspecialcharsbx($value) ?></option>
                            <?php
                        } ?>
                    </select>
                </td>
            </tr>

            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for="pages_select"><?= Loc::getMessage("uplab.tilda_SELECT_PAGE"); ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <select size="1" name="PAGE" id="pages_select" style="max-width: 453px;<?= (!empty($arPages) && $pagesError === null) ? '' : ' display: none;' ?>">
                        <?php
                        foreach ($arPages as $id => $name) { ?>
                            <option value="<?= htmlspecialcharsbx($id) ?>" <?= ($editPage && (int)$id === $editPage) ? "selected" : "" ?>><?= htmlspecialcharsbx($name) ?></option>
                            <?php
                        } ?>
                    </select>
                    <?php
                    // Пустой список и несработавший запрос выглядят одинаково —
                    // пустым выпадающим списком, поэтому состояние подписывается
                    // текстом, а при ошибке даётся кнопка повтора.
                    $initialMessage = '';
                    if ($pagesError !== null) {
                        $initialMessage = Loc::getMessage('uplab.tilda_PAGES_LOAD_ERROR');
                    } elseif (empty($arPages)) {
                        $initialMessage = Loc::getMessage('uplab.tilda_PAGES_EMPTY');
                    }
                    ?>
                    <span id="pages_message"><?= htmlspecialcharsbx($initialMessage) ?></span>
                    <button type="button" id="pages_retry" style="<?= ($pagesError !== null) ? '' : 'display: none;' ?>"><?= htmlspecialcharsbx(Loc::getMessage('uplab.tilda_RETRY')) ?></button>
                </td>
            </tr>

            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for="no_template_checkbox"><?= Loc::getMessage("uplab.tilda_NO_TEMPLATE") ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <input type="checkbox" name="no_template" value="Y" id="no_template_checkbox" class="adm-designed-checkbox" <?= $editHideTemplate ? "checked" : "" ?>>
                    <label class="adm-designed-checkbox-label" for="no_template_checkbox" title=""></label>
                </td>
            </tr>
            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for="move_resources_to"><?= Loc::getMessage('uplab.tilda_MOVE_TILDA_ASSETS'); ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <select size="1" name="move_resources_to" id="move_resources_to">
                        <?php
                        foreach ($moveResourcesOptions as $optValue => $optLabel) { ?>
                            <option value="<?= htmlspecialcharsbx($optValue) ?>" <?= ($editMoveResources === $optValue) ? "selected" : "" ?>><?= htmlspecialcharsbx($optLabel) ?></option>
                            <?php
                        } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="bxcompprop-last-empty-cell" style="height: 119px;"></td>
            </tr>
        </table>
    </form>
    <script type="text/javascript">
        <?php // Draw the Save and Close buttons ?>
        BX.WindowManager.Get().SetButtons([BX.CDialog.prototype.btnSave, BX.CDialog.prototype.btnCancel]);

        (function () {
            var MESSAGES = <?= $jsonForScript($jsMessages) ?>;
            var POST_URL = '/bitrix/tools/uplab.tilda_post.php';

            var projectSelect = document.getElementById('projects_select');
            var pageSelect = document.getElementById('pages_select');
            var messageBox = document.getElementById('pages_message');
            var retryButton = document.getElementById('pages_retry');

            if (!projectSelect || !pageSelect || !messageBox || !retryButton) {
                return;
            }

            // Номер последнего отправленного запроса: ответы более ранних
            // запросов игнорируются, иначе медленный ответ по прежнему проекту
            // перезапишет список только что выбранного.
            var lastRequest = 0;

            // Уже полученные списки страниц: повторный выбор того же проекта
            // берётся из памяти окна, без обращения к серверу. Кэш живёт только
            // пока открыт попап — иначе страница, созданная в Tilda только что,
            // не появилась бы до перезагрузки админки.
            var pagesCache = {};

            // Перебор проектов с клавиатуры меняет значение селекта на каждом
            // шаге; запрос уходит только после паузы, а не на каждый шаг.
            var loadTimer = null;
            var LOAD_DELAY = 250;

            // Предел ожидания ответа сервера. Больше собственного таймаута
            // запроса к Tilda (15 секунд по умолчанию), чтобы не обрывать
            // работающий запрос, но конечный.
            var REQUEST_TIMEOUT = 30000;

            // Первичный список отрисован сервером — кладём его в кэш сразу,
            // чтобы возврат к исходному проекту не стоил запроса.
            <?php
            if ($pagesError === null) { ?>
            pagesCache[<?= (int)$selectedProject ?>] = <?= $jsonForScript($jsPages) ?>;
            <?php
            } ?>

            function showMessage(text, withRetry) {
                messageBox.textContent = text || '';
                retryButton.style.display = withRetry ? '' : 'none';
            }

            function fillPages(pages) {
                pageSelect.innerHTML = '';

                for (var i = 0; i < pages.length; i++) {
                    var option = document.createElement('option');
                    option.value = pages[i].id;
                    option.textContent = pages[i].title;
                    pageSelect.appendChild(option);
                }

                pageSelect.style.display = '';
                showMessage('', false);
            }

            function loadPages(force) {
                var projectId = parseInt(projectSelect.value, 10) || 0;
                var requestId = ++lastRequest;

                if (!force && pagesCache.hasOwnProperty(projectId)) {
                    if (pagesCache[projectId].length) {
                        fillPages(pagesCache[projectId]);
                    } else {
                        pageSelect.style.display = 'none';
                        pageSelect.innerHTML = '';
                        showMessage(MESSAGES.empty, false);
                    }

                    return;
                }

                projectSelect.disabled = true;
                pageSelect.style.display = 'none';
                pageSelect.innerHTML = '';
                showMessage(MESSAGES.loading, false);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', POST_URL, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                // Без ограничения зависший запрос оставил бы «Загрузка...» и
                // заблокированный выбор проекта навсегда.
                xhr.timeout = REQUEST_TIMEOUT;

                // Ответ устаревшего запроса игнорируется: селект разблокирует
                // актуальный, который ещё выполняется.
                function isCurrent() {
                    return requestId === lastRequest;
                }

                function failRequest(text) {
                    if (!isCurrent()) {
                        return;
                    }

                    projectSelect.disabled = false;
                    // Неудачу не запоминаем: следующий выбор этого проекта
                    // должен снова попробовать загрузить список.
                    showMessage(text || MESSAGES.error, true);
                }

                xhr.onload = function () {
                    if (!isCurrent()) {
                        return;
                    }

                    var response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        response = null;
                    }

                    if (!response || response.status !== 'success') {
                        failRequest(response ? response.message : '');
                        return;
                    }

                    projectSelect.disabled = false;

                    var pages = response.pages || [];
                    pagesCache[projectId] = pages;

                    if (response.empty || !pages.length) {
                        showMessage(MESSAGES.empty, false);
                        return;
                    }

                    fillPages(pages);
                };

                xhr.ontimeout = function () {
                    failRequest(MESSAGES.timeout);
                };

                xhr.onerror = function () {
                    failRequest(MESSAGES.error);
                };

                xhr.onabort = function () {
                    failRequest(MESSAGES.error);
                };

                xhr.send(
                    'getPages=Y&projectId=' + encodeURIComponent(projectId) +
                    '&sessid=' + encodeURIComponent(BX.bitrix_sessid())
                );
            }

            projectSelect.addEventListener('change', function () {
                if (loadTimer) {
                    clearTimeout(loadTimer);
                }

                loadTimer = setTimeout(function () {
                    loadTimer = null;
                    loadPages(false);
                }, LOAD_DELAY);
            });

            // Повтор после ошибки идёт мимо кэша и без задержки: пользователь
            // нажал кнопку осознанно.
            retryButton.addEventListener('click', function () {
                loadPages(true);
            });
        })();
    </script>
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin_after.php");
