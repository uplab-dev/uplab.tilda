<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;

global $APPLICATION;

if (!Loader::includeModule('uplab.tilda')) {
    return [];
}

// Подключаем диалоги BX.UI.Dialogs.MessageBox (модуль ui, ядро >= 20) для уведомлений
// и подтверждений; при отсутствии модуля ui script.js откатится на alert()/confirm().
if (Loader::includeModule('ui')) {
    Extension::load('ui.dialogs.messagebox');
}

Asset::getInstance()->addJs("/bitrix/js/uplab.tilda/script.min.js");

$aMenu = [
    "parent_menu" => "global_menu_content",
    "section"     => "Uplab",
    "sort"        => 50,
    "text"        => Loc::getMessage("uplab.tilda_MENU_TITLE"),
    "icon"        => "blog_menu_icon",
    "page_icon"   => "util_page_icon",
    "items_id"    => "uplab_tilda",
    "items"       => [
        [
            "text" => Loc::getMessage('uplab.tilda_PAGES'),
            "url"  => "/bitrix/admin/uplab.tilda_cache_table.php",
        ],
        [
            "text" => Loc::getMessage('uplab.tilda_CLEAR_CACHE_LIST_MENU_TITLE'),
            // Языковая строка уходит в JS-контекст — экранируем так же, как в
            // списке страниц: апостроф в переводе иначе ломает вызов.
            "url"  => "javascript:uTildaClearCacheList('" . CUtil::JSEscape(Loc::getMessage("uplab.tilda_CLEAR_CACHE_LIST_CONFIRM")) . "');",
        ],
        [
            "text" => Loc::getMessage('uplab.tilda_CLEAR_CACHE_MENU_TITLE'),
            "url"  => "javascript:uTildaClearCache('" . CUtil::JSEscape(Loc::getMessage("uplab.tilda_CLEAR_CACHE_CONFIRM")) . "');",
        ]
    ]
];

return $aMenu;