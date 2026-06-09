<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Uplab\Tilda\Cache;

const STOP_STATISTICS = true;
const NO_AGENT_CHECK = true;
const DisableEventsCheck = true;
const BX_SECURITY_SHOW_MESSAGE = true;
const PUBLIC_AJAX_MODE = true;
const NOT_CHECK_PERMISSIONS = true;
const ADMIN_MODULE_NAME = 'uplab.tilda';

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

header('Content-Type: application/x-javascript; charset=' . LANG_CHARSET);

global $APPLICATION;
global $USER;

if (!Loader::includeModule(ADMIN_MODULE_NAME)) {
    \CMain::FinalActions(Loc::getMessage('uplab.tilda_NO_MODULE'));
}

if (!$USER->IsAuthorized() || $APPLICATION->GetGroupRight(ADMIN_MODULE_NAME) < 'W') {
    \CMain::FinalActions(Loc::getMessage('ACCESS_DENIED'));
}

if (check_bitrix_sessid()) {
    $request = Bitrix\Main\Context::getCurrent()->getRequest();

    if ($request->get('clearCache') === 'Y') {
        Cache::clearAllCache();
        echo Loc::getMessage('uplab.tilda_CACHE_CLEARED');
    }

    if ($request->get('clearCacheList') === 'Y') {
        Cache::clearListCache();
        echo Loc::getMessage('uplab.tilda_CACHE_LIST_CLEARED');
    }
} else {
    echo Loc::getMessage('uplab.tilda_SESSION_EXPIRED');
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin_after.php");