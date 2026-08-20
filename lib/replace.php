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

namespace Uplab\Tilda;

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Uplab\Tilda\Diag\Logger;
use Uplab\Tilda\Enum\MoveResourcesTarget;
use Uplab\Tilda\Service\Cache;

Loc::loadMessages(__FILE__);

/**
 * Замена тегов `[UPLABTILDA ...]` на HTML-контент страниц Tilda в буфере вывода.
 *
 * Вызывается по событию `main:OnEndBufferContent`, разбирает параметры тега
 * (`PROJECT`, `PAGE`, `HIDEPAGETEMPLATE`, `MOVERESOURCESTO`) и выбирает один из
 * режимов вставки контента, получая HTML через {@see Common}. Также чистит
 * теги из индексируемого поиском контента.
 *
 * @package Uplab\Tilda
 */
class Replace
{
    /**
     * Находит в буфере вывода все теги `[UPLABTILDA ...]` и заменяет их на
     * контент соответствующих страниц Tilda.
     *
     * В публичной части сайта (не в админке) для каждого вхождения вызывается
     * {@see Replace::replaceContent()}; дополнительно совместимостный фикс
     * jQuery `.load` → `.on('load', ...)` и удаление кода в маркерах
     * `<!--UTilda-->...<!--EndUTilda-->`.
     *
     * @param string $content Буфер вывода страницы (передаётся по ссылке и изменяется).
     * @return bool false в админ-разделе, иначе true.
     */
    static function tagReplace(&$content)
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if ($request->isAdminSection()) {
            return false;
        }

        // Replace all occurrences of tag [UPLABTILDA ... ]
        if (preg_match_all("/\[(UPLABTILDA [^\]]+)\]/", $content, $matches)) {
            Logger::debug('Tilda tags found on page', [
                'count' => count($matches[1]),
                'uri'   => $request->getRequestUri(),
            ]);

            if (is_array($matches[1])) {
                foreach ($matches[1] as $k => $match) {
                    $content = self::replaceContent($content, $match, $matches[0][$k]);
                }
            } else {
                $content = self::replaceContent($content, $matches[1], $matches[0]);
            }

            // Replace JQuery .load method with .on
            $content = str_replace("$(window).load(function()", "$(window).on('load', function ()", $content);

            // Remove content marked with tag <!--UTilda--><!--EndUTilda-->
            Helper::removeCommentedCode($content);
        }

        return true;
    }

    /**
     * Заменяет одно вхождение тега Tilda на HTML страницы в зависимости от
     * режима вставки.
     *
     * Разбирает параметры тега и выбирает режим:
     * — `HIDEPAGETEMPLATE=Y` — полный HTML без шаблона сайта
     *   ({@see Common::getPageFullContent()});
     * — `MOVERESOURCESTO=HEADEND|BODYEND` — вынос ассетов в конец head/body и
     *   замена тега на тело страницы ({@see Common::getPageParts()});
     * — по умолчанию — встраивание контента в шаблон сайта
     *   ({@see Common::getPageContent()}).
     *
     * @param string $content  Буфер вывода страницы.
     * @param string $match    Содержимое тега без скобок (например, `UPLABTILDA PROJECT=.. PAGE=..`).
     * @param string $tildaTag Полный текст тега вместе со скобками `[...]` для замены.
     * @return string Контент с выполненной заменой.
     */
    static function replaceContent($content, $match, $tildaTag)
    {
        // Parsing tag service parameters: PROJECT, PAGE, HIDEPAGETEMPLATE, MOVERESOURCESTO
        $params = array();

        $explodedParams = preg_split("~(\s|&nbsp;)+~u", $match);
        foreach ($explodedParams as $value) {
            $keyValue = explode('=', $value, 2);
            $key = str_replace("amp;", "", $keyValue[0]);
            $params[$key] = $keyValue[1] ?? '';
        }

        $pageId = (int)($params['PAGE'] ?? 0);

        if (
            !empty($params['HIDEPAGETEMPLATE']) &&
            $params['HIDEPAGETEMPLATE'] === "Y"
        ) {
            // Case: Don't display site template
            Logger::debug('Replacing tag', ['pageId' => $pageId, 'mode' => 'HIDEPAGETEMPLATE']);

            $tildaContent = Common::getPageFullContent($pageId);

            if ($tildaContent !== '') {
                $content = self::injectNotice($tildaContent, self::staleNotice($pageId));
            } else {
                // Пустой ответ Tilda не должен обнулять весь буфер: иначе
                // посетитель получает документ нулевой длины с кодом 200.
                // Оставляем страницу сайта, но вырезаем сам тег — служебная
                // строка на виду у читателя недопустима.
                $content = str_replace($tildaTag, '', $content);

                Logger::error(Loc::getMessage('uplab.tilda_LOG_EMPTY_CONTENT'), ['pageId' => $pageId]);
            }
        } else {
            $moveTarget = MoveResourcesTarget::fromMixed($params['MOVERESOURCESTO'] ?? '');
            if (MoveResourcesTarget::shouldMove($moveTarget)) {
                // Case: Display site template case + replace Tilda tag + move Tilda assets
                Logger::debug('Replacing tag', ['pageId' => $pageId, 'mode' => 'MOVERESOURCESTO=' . $moveTarget]);

                $parts = Common::getPageParts($pageId);

                // Insert Tilda assets to the place defined by the tag (head/body end)
                $content = MoveResourcesTarget::injectAssets($moveTarget, $content, $parts['assets']);

                // Replace Tilda tag with content
                $content = str_replace($tildaTag, self::staleNotice($pageId) . $parts['html'], $content);
            } else {
                // Case: Display site template case + replace Tilda tag
                Logger::debug('Replacing tag', ['pageId' => $pageId, 'mode' => 'default']);

                $html = Common::getPageContent($pageId, $params);

                $content = str_replace($tildaTag, self::staleNotice($pageId) . $html, $content);
            }
        }

        return $content;
    }

    /**
     * Формирует пометку о том, что страница отдана из устаревшей копии кэша.
     *
     * HTML-комментарий выводится всегда — по нему видно состояние в исходном
     * коде страницы и в логах внешних проверок. Видимая полоса показывается
     * только пользователям с правом на модуль: контент-менеджер должен понимать,
     * что на проде старая версия страницы, а посетитель не должен видеть
     * служебных сообщений.
     *
     * @param int $pageId Идентификатор страницы Tilda.
     * @return string Пустая строка, если страница отдана из актуального кэша
     *                или загружена заново.
     */
    private static function staleNotice($pageId)
    {
        $date = Cache::getStaleServed($pageId);

        if ($date === null) {
            return '';
        }

        // Последовательность «--» внутри комментария закрыла бы его раньше
        // времени, поэтому дату приводим к безопасному виду.
        $comment = '<!-- uplab.tilda: stale cache, page=' . (int)$pageId
            . ', fetched ' . str_replace(['--', '>'], ['-', ''], $date) . ' -->' . "\n";

        if (!self::isStaffViewer()) {
            return $comment;
        }

        return $comment
            . '<div class="uplab-tilda-stale" style="margin:0 0 12px;padding:10px 14px;'
            . 'border:1px solid #f0b429;border-radius:4px;background:#fff8e1;color:#5c4813;'
            . 'font:14px/1.45 Arial,Helvetica,sans-serif;">'
            . htmlspecialcharsbx(Loc::getMessage('uplab.tilda_STALE_BAR', ['#DATE#' => $date]))
            . '</div>' . "\n";
    }

    /**
     * Вставляет пометку в начало готового документа Tilda (режим
     * `HIDEPAGETEMPLATE=Y`, где шаблона сайта нет).
     *
     * @param string $document Полный HTML страницы Tilda.
     * @param string $notice   Пометка либо пустая строка.
     * @return string
     */
    private static function injectNotice($document, $notice)
    {
        if ($notice === '') {
            return $document;
        }

        if (preg_match('/<body\b[^>]*>/i', $document)) {
            $injected = preg_replace_callback(
                '/<body\b[^>]*>/i',
                function ($matches) use ($notice) {
                    return $matches[0] . $notice;
                },
                $document,
                1
            );

            if (is_string($injected)) {
                return $injected;
            }
        }

        // Документ без <body> — не тот HTML, который отдаёт Tilda, но пометку
        // всё равно не теряем.
        return $notice . $document;
    }

    /**
     * Показывать ли видимую полосу: пометка предназначена сотрудникам, для
     * посетителя сайта вывод не меняется.
     *
     * @return bool
     */
    private static function isStaffViewer()
    {
        global $APPLICATION;

        return ($APPLICATION instanceof \CMain)
            && $APPLICATION->GetGroupRight(Common::$module_id) >= 'R';
    }

    /**
     * Вырезает теги `[UPLABTILDA ...]` из полей `TITLE` и `BODY` перед
     * индексацией контента инфоблоков поиском, чтобы они не попадали в индекс.
     *
     * Предназначен для обработчика события поиска (`OnSearchGetContent`/
     * `BeforeIndex`).
     *
     * @param array $arFields Поля индексируемого элемента.
     * @return array Поля с очищенными от тегов `TITLE` и `BODY`.
     */
    static function removeFromIndex($arFields)
    {
        if ($arFields["MODULE_ID"] === "iblock") {
            $arFields["TITLE"] = preg_replace(
                "/\[(UPLABTILDA [^\]]+)\]/",
                "",
                $arFields["TITLE"]
            );

            $arFields["BODY"] = preg_replace(
                "/\[(UPLABTILDA [^\]]+)\]/",
                "",
                $arFields["BODY"]
            );
        }

        return $arFields;
    }
}
