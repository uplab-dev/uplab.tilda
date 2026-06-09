<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;

$request = HttpApplication::getInstance()->getContext()->getRequest();
$moduleId = htmlspecialcharsbx($request["mid"] != "" ? $request["mid"] : $request["id"]);

Loader::includeModule($moduleId);

$aTabs = array(
    array(
        'DIV'     => 'tilda_options',
        'TAB'     => Loc::getMessage('uplab.tilda_SETTINGS_TAB_NAME'),
        'OPTIONS' => array(
            Loc::getMessage('uplab.tilda_SETTINGS_TAB_NAME'),
            array(
                'UPT_PUBLIC_KEY',
                Loc::getMessage('uplab.tilda_PUBLIC_KEY'),
                null,
                array('text', 30),
            ),
            array(
                'UPT_SECRET_KEY',
                Loc::getMessage('uplab.tilda_SECRET_KEY'),
                null,
                array('text', 30),
            ),
            Loc::getMessage('uplab.tilda_BASE_SETTINGS'),
            array(
                'UPT_API_URL',
                Loc::getMessage('uplab.tilda_API_URL'),
                \Uplab\Tilda\Common::DEFAULT_API_URL,
                array('text', 50),
            ),
            array(
                'UPT_CURLOPT_TIMEOUT',
                Loc::getMessage('uplab.tilda_TIMEOUT'),
                15,
                array('text', 30),
            ),
            array(
                'UPT_CURLOPT_CONNECTTIMEOUT',
                Loc::getMessage('uplab.tilda_CONNECTTIMEOUT'),
                15,
                array('text', 30),
            ),
        )
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strlen($_REQUEST['save']) > 0 && check_bitrix_sessid()) {
    foreach ($aTabs as $aTab) {
        __AdmSettingsSaveOptions($moduleId, $aTab['OPTIONS']);
    }

    LocalRedirect(
        $APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&mid_menu=1&mid=' . urlencode($moduleId) .
        '&tabControl_active_tab=' . urlencode($_REQUEST['tabControl_active_tab'] ?? '')
    );
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>
<form method='post' action='' name='bootstrap'>
    <?php
    $tabControl->Begin();

    foreach ($aTabs as $aTab) {
        $tabControl->BeginNextTab();
        __AdmSettingsDrawList($moduleId, $aTab['OPTIONS']);
    }

    $tabControl->Buttons(
        array(
            'btnApply'      => false,
            'btnCancel'     => false,
            'btnSaveAndAdd' => false
        )
    ); ?>

    <?= bitrix_sessid_post(); ?>
    <?php
    $tabControl->End(); ?>
</form>