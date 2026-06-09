<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Uplab\Tilda\Common;
use Uplab\Tilda\Enum\MoveResourcesTarget;
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
    \CMain::FinalActions(Loc::getMessage('uplab.tilda_NO_MODULE'));
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

// Build the resource-move dropdown options from the single source of truth (enum).
$moveResourcesOptions = array();
foreach (MoveResourcesTarget::langSuffixes() as $optValue => $langSuffix) {
    $moveResourcesOptions[$optValue] = Loc::getMessage('uplab.tilda_' . $langSuffix);
}

// Receive a list of projects
$arProjects = Common::getAssocProjectsList();

if (empty($arProjects)) {
    \CMain::FinalActions(GetMessage("uplab.tilda_NO_PROJECTS") . " <a href=\"/bitrix/admin/settings.php?lang=" . LANGUAGE_ID . "&mid=uplab.tilda\">" . GetMessage("uplab.tilda_NO_KEYS") . "</a>");
}

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
            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for="projects_select"><?= Loc::getMessage("uplab.tilda_SELECT_PROJECT"); ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <select size="1" name="PROJECT" id="projects_select">
                        <?php
                        foreach ($arProjects as $key => $value) { ?>
                            <option value="<?= htmlspecialcharsbx($key) ?>" <?= ($editProject && (int)$key === $editProject) ? "selected" : "" ?>><?= htmlspecialcharsbx($value) ?></option>
                            <?php
                        } ?>
                    </select>
                </td>
            </tr>

            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for=""><?= Loc::getMessage("uplab.tilda_SELECT_PAGE"); ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <?php
                    $j = 0;
                    foreach ($arProjects as $key => $value) {
                        $j++;
                        $arPages = Common::getAssocPagesList($key);
                        // Show the list for the edited project (or the first one when inserting a new tag).
                        $showThis = $editProject ? ((int)$key === $editProject) : ($j === 1); ?>
                        <select size="1" name="PAGE_<?= htmlspecialcharsbx($key) ?>" id="page_select_<?= htmlspecialcharsbx($key) ?>" class="js-project-page" style="max-width: 453px; <?= $showThis ? "" : "display: none;" ?>">
                            <?php
                            foreach ($arPages as $id => $name) { ?>
                                <option value="<?= htmlspecialcharsbx($id) ?>" <?= ($editPage && (int)$id === $editPage) ? "selected" : "" ?>><?= htmlspecialcharsbx($name) ?></option>
                                <?php
                            } ?>
                        </select>
                        <?php
                    } ?>
                </td>
            </tr>

            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for=""><?= Loc::getMessage("uplab.tilda_NO_TEMPLATE") ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <input type="checkbox" name="no_template" id="no_template_checkbox" class="adm-designed-checkbox" <?= $editHideTemplate ? "checked" : "" ?>>
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

        <?php // Show only the selection from the page of the selected project ?>
        BX.ready(function () {
            var projectSelect = document.querySelector("select[name=PROJECT]");
            if (!projectSelect) {
                return;
            }
            projectSelect.addEventListener("change", function () {
                var project = this.value;
                var pages = document.querySelectorAll(".js-project-page");
                for (var i = 0; i < pages.length; i++) {
                    pages[i].style.display = (pages[i].name === "PAGE_" + project) ? "" : "none";
                }
            });
        });
    </script>
<?php
// If the data is selected and received, close the window and insert the tag
if (!empty($request->get('PROJECT'))) {
    $project = (int)$request->get('PROJECT');
    $page = (int)$request->get('PAGE_' . $project);

    $tagStr = "UPLABTILDA PROJECT={$project} PAGE={$page}";

    $no_template = $request->get('no_template');
    if ($no_template === 'Y') {
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
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin_after.php");