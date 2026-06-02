<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Uplab\Tilda\Common;
use Bitrix\Main\Localization\Loc;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

Loader::includeModule("uplab.tilda");

// Receive a list of projects
$arProjects = Common::getAssocProjectsList();

if (empty($arProjects)) {
	echo GetMessage("uplab.tilda_NO_PROJECTS") . " <a href=\"" . SITE_DIR . "/bitrix/admin/settings.php?lang=" . LANGUAGE_ID . "&mid=uplab.tilda\">" . GetMessage("uplab.tilda_NO_KEYS") . "</a>";
	return false;
}

// Draw the form
?>
	<form id="page_select_form">
		<table class="bxcompprop-content-table">
			<tr class="bxcompprop-prop-tr">
				<td class="bxcompprop-cont-table-title" colspan="2"><?= Loc::getMessage("uplab.tilda_PAGE_SELECT"); ?></td>
			</tr>
			<tr class="bxcompprop-prop-tr">
				<td class="bxcompprop-cont-table-l"><label class="bxcompprop-label" for="projects_select"><?= Loc::getMessage("uplab.tilda_SELECT_PROJECT"); ?></label></td>
				<td class="bxcompprop-cont-table-r">
					<select size="1" name="PROJECT" id="projects_select">
						<?php foreach ($arProjects as $key => $value) { ?>
							<option value="<?= $key ?>"><?= $value ?></option>
						<?php } ?>
					</select>
				</td>
			</tr>

			<tr class="bxcompprop-prop-tr">
				<td class="bxcompprop-cont-table-l"><label class="bxcompprop-label" for=""><?= Loc::getMessage("uplab.tilda_SELECT_PAGE"); ?></label></td>
				<td class="bxcompprop-cont-table-r">
					<?php $j = 0; ?>
					<?php foreach ($arProjects as $key => $value) { ?>
						<?php $j++; ?>
						<?php $arPages = Common::getAssocPagesList($key); ?>

						<select size="1" name="PAGE_<?= $key ?>" id="page_select_<?= $key ?>" class="js-project-page" style="max-width: 453px; <?= ($j == 1) ? "" : "display: none;" ?>">
							<?php foreach ($arPages as $id => $name) { ?>
								<option value="<?= $id ?>"><?= $name ?></option>
							<?php } ?>
						</select>

					<?php } ?>
				</td>
			</tr>

			<tr class="bxcompprop-prop-tr">
				<td class="bxcompprop-cont-table-l">
					<label class="bxcompprop-label" for="">
						<?= Loc::getMessage("uplab.tilda_NO_TEMPLATE"); ?>
					</label>
				</td>
				<td class="bxcompprop-cont-table-r">
					<input type="checkbox" name="no_template" id="no_template_checkbox" class="adm-designed-checkbox">
					<label class="adm-designed-checkbox-label" for="no_template_checkbox" title=""></label>
				</td>
			</tr>

            <tr class="bxcompprop-prop-tr">
                <td class="bxcompprop-cont-table-l">
                    <label class="bxcompprop-label" for="move_resources_to"><?= Loc::getMessage('uplab.tilda_MOVE_TILDA_ASSETS'); ?></label>
                </td>
                <td class="bxcompprop-cont-table-r">
                    <select size="1" name="move_resources_to" id="move_resources_to">
                        <option value="" selected><?= Loc::getMessage('uplab.tilda_MOVE_TILDA_ASSETS_NONE'); ?></option>
                        <option value="headend"><?= Loc::getMessage('uplab.tilda_MOVE_TILDA_ASSETS_HEADEND'); ?></option>
                        <option value="bodyend"><?= Loc::getMessage('uplab.tilda_MOVE_TILDA_ASSETS_BODYEND'); ?></option>
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
        $(function () {
            $("select[name=PROJECT]").on("change", function () {
                var $this = $(this);
                var project = $this.val();

                $(".js-project-page")
                    .hide()
                    .filter('[name="PAGE_' + project + '"]')
                    .show();
            });
        });
	</script>
<?php
// If the data is selected and received, close the window and insert the tag
$request = Context::getCurrent()->getRequest();

if (!empty($request->get('PROJECT'))) {
	$project = $request->get('PROJECT');
	$page = $request->get('PAGE_' . $request->get('PROJECT'));

	$tagStr = "UPLABTILDA PROJECT={$project} PAGE={$page}";

    $no_template = $request->get('no_template');
	if ($no_template === 'Y') {
		$tagStr .= ' HIDEPAGETEMPLATE=Y';
	}

    $move_resources_to = $request->get('move_resources_to');
    if ($move_resources_to === 'headend') {
        $tagStr .= ' MOVERESOURCESTO=HEADEND';
    } elseif ($move_resources_to === 'bodyend') {
        $tagStr .= ' MOVERESOURCESTO=BODYEND';
    }
	?>
	<script>
        <?php // Close the window ?>
        BX.WindowManager.Get().AllowClose();
        BX.WindowManager.Get().Close();

        <?php // Insert a line into the visual editor ?>
        window.tildaTag('[' + '<?= $tagStr ?>' + ']');
	</script>
	<?php
}
?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
