<?php

/**
 * @global CMain $APPLICATION
 */

use Bitrix\Main\Localization\Loc;

global $APPLICATION;

$moduleId = 'uplab.tilda';
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="<?= $moduleId ?>">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">

    <?php CAdminMessage::ShowMessage(Loc::getMessage("{$moduleId}_UNINSTALL_WIZARD_DESC")); ?>

    <p>
        <input type="hidden" name="delete_cache" value="N">
        <input type="checkbox" name="delete_cache" id="delete_cache" value="Y" checked>
        <label for="delete_cache"><?= htmlspecialcharsbx(Loc::getMessage("{$moduleId}_UNINSTALL_CACHE_FILES")) ?></label>
    </p>
    <p>
        <input type="hidden" name="delete_table" value="N">
        <input type="checkbox" name="delete_table" id="delete_table" value="Y" checked>
        <label for="delete_table"><?= htmlspecialcharsbx(Loc::getMessage("{$moduleId}_UNINSTALL_DB_TABLE")) ?></label>
    </p>
    <p>
        <input type="hidden" name="delete_logs" value="N">
        <input type="checkbox" name="delete_logs" id="delete_logs" value="Y" checked>
        <label for="delete_logs"><?= htmlspecialcharsbx(Loc::getMessage("{$moduleId}_UNINSTALL_LOG_FILES")) ?></label>
    </p>
    <p>
        <input type="hidden" name="delete_settings" value="N">
        <input type="checkbox" name="delete_settings" id="delete_settings" value="Y" checked>
        <label for="delete_settings"><?= htmlspecialcharsbx(Loc::getMessage("{$moduleId}_UNINSTALL_SETTINGS")) ?></label>
    </p>

    <input type="submit" name="inst" value="<?= htmlspecialcharsbx(Loc::getMessage("{$moduleId}_UNINSTALL_SUBMIT")) ?>">
    &nbsp;
    <a href="/bitrix/admin/partner_modules.php?lang=<?= urlencode(LANGUAGE_ID) ?>" class="adm-btn">
        <?= htmlspecialcharsbx(Loc::getMessage("{$moduleId}_UNINSTALL_CANCEL")) ?>
    </a>
</form>
