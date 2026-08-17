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

/**
 * @global $find
 * @global $find_type
 */

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Uplab\Tilda\Service\Cache;
use Uplab\Tilda\Model\CacheTable;
use Bitrix\Main\UI\AdminPageNavigation;
use Bitrix\Main\UI\Filter;

const ADMIN_MODULE_NAME = 'uplab.tilda';

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

global $APPLICATION;

$userRights = $APPLICATION->GetGroupRight(ADMIN_MODULE_NAME);

if (!Loader::includeModule(ADMIN_MODULE_NAME) || $userRights < "R") {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

$APPLICATION->SetTitle(Loc::getMessage('uplab.tilda_PAGE_TITLE'));

/** @var CacheTable::class $entity */
$entity = CacheTable::class;

$sTableID = CacheTable::getTableName();
$sorting = new CAdminUiSorting($sTableID, "NAME", "ASC");
$lAdmin = new CAdminUiList($sTableID, $sorting);

/*
 * Обработка действий над списком
 */
if (($ids = $lAdmin->GroupAction()) && $userRights >= 'W' && check_bitrix_sessid()) {
    if ($lAdmin->IsGroupActionToAll()) {
        $tagsData = CacheTable::getList([
            'select' => ['TAG']
        ])->fetchAll();

        foreach ($tagsData as $item) {
            $ids[] = $item['TAG'];
        }
        unset($item, $tagsData);
    }

    $action = $lAdmin->GetAction();
    $connection = Application::getConnection();
    foreach ($ids as $tag) {
        // TAG по инварианту — md5(url) (32 hex-символа); отсекаем всё прочее,
        // чтобы произвольное значение не ушло в cleanDir()/delete().
        if (!preg_match('/^[a-f0-9]{32}$/i', (string)$tag)) {
            continue;
        }

        switch ($action) {
            case 'delete':
                @set_time_limit(0);
                $connection->startTransaction();

                Cache::clearPageCache($tag);

                $result = CacheTable::delete($tag);
                if (!$result->isSuccess()) {
                    $connection->rollbackTransaction();
                    $err = implode(', ', $result->getErrorMessages());
                    $lAdmin->AddGroupError(GetMessage("DELETE_ERROR") . $err, $tag);
                } else {
                    $connection->commitTransaction();
                }
                break;
        }
    }

    if ($lAdmin->hasGroupErrors()) {
        $adminSidePanelHelper->sendJsonErrorResponse($lAdmin->getGroupErrors());
    } else {
        $adminSidePanelHelper->sendSuccessResponse();
    }
}


$arFilter = [];

$filterFields = [
    ["id" => "NAME", "name" => Loc::getMessage('uplab.tilda_HEADER_NAME'), "default" => true],
    ["id" => "TAG", "name" => Loc::getMessage('uplab.tilda_HEADER_TAG'), "default" => true],
    ["id" => "PAGE_ID", "name" => Loc::getMessage('uplab.tilda_HEADER_PAGE_ID'), "default" => true],
    ["id" => "PROJECT_ID", "name" => Loc::getMessage('uplab.tilda_HEADER_PROJECT_ID'), "default" => true],
    ["id" => "DATE", "name" => Loc::getMessage('uplab.tilda_HEADER_DATE'), "default" => true, 'type' => 'date']
];
$filterOption = new Filter\Options($sTableID);
$filter = $filterOption->getFilter($filterFields);

if (!empty($filter['TAG'])) {
    $arFilter["%TAG"] = $filter['TAG'];
}
if (!empty($filter['NAME'])) {
    $arFilter["%NAME"] = $filter['NAME'];
}
if (!empty($filter['PAGE_ID'])) {
    $arFilter["PAGE_ID"] = (int)$filter['PAGE_ID'];
}
if (!empty($filter['PROJECT_ID'])) {
    $arFilter["PROJECT_ID"] = (int)$filter['PROJECT_ID'];
}
if (!empty($filter['DATE_from'])) {
    $arFilter[">=DATE"] = $filter['DATE_from'];
}
if (!empty($filter['DATE_to'])) {
    $arFilter["<=DATE"] = $filter['DATE_to'];
}


InitSorting();

$sortOrder = mb_strtoupper($sorting->getOrder());
if ($sortOrder !== "DESC") {
    $sortOrder = "ASC";
}

$nav = new AdminPageNavigation("nav-uplabtilda-cache_table");

$listParams = [
    'filter'      => $arFilter,
    'order'       => ["NAME" => $sortOrder],
    'count_total' => true,
];

if (!(isset($_REQUEST["mode"]) && $_REQUEST["mode"] === "excel")) {
    $listParams['offset'] = $nav->getOffset();
    $listParams['limit'] = $nav->getLimit();
}

$entityList = $entity::getList($listParams);
$count = $entityList->getCount();
$nav->setRecordCount($count);

if ($lAdmin->isTotalCountRequest()) {
    $lAdmin->sendTotalCountResponse($count);
}

$lAdmin->setNavigation($nav, Loc::getMessage("uplab.tilda_NAVIGATION"));

$lAdmin->AddHeaders([
    ["id" => "NAME", "content" => Loc::getMessage('uplab.tilda_HEADER_NAME'), "sort" => "NAME", "default" => true],
    ["id" => "TAG", "content" => Loc::getMessage('uplab.tilda_HEADER_TAG'), "sort" => "TAG", "default" => true],
    ["id" => "PAGE_ID", "content" => Loc::getMessage('uplab.tilda_HEADER_PAGE_ID'), "sort" => "PAGE_ID", "default" => true],
    ["id" => "PROJECT_ID", "content" => Loc::getMessage('uplab.tilda_HEADER_PROJECT_ID'), "sort" => "PROJECT_ID", "default" => true],
    ["id" => "DATE", "content" => Loc::getMessage('uplab.tilda_HEADER_DATE'), "sort" => "DATE", "default" => true]
]);

$userItemCache = [];

while ($item = $entityList->fetch()) {
    $itemId = $item['TAG'];

    $row = &$lAdmin->AddRow($itemId, $item);

    $row->AddViewField('NAME', htmlspecialcharsbx($item['NAME']));
    $row->AddViewField('TAG', htmlspecialcharsbx($item['TAG']));
    $row->AddViewField('PAGE_ID', (int)$item['PAGE_ID']);
    $row->AddViewField('PROJECT_ID', (int)$item['PROJECT_ID']);
    $row->AddViewField('DATE', htmlspecialcharsbx((string)$item['DATE']));

    $arActions = [];

    if ($userRights >= 'W') {
        $arActions[] = [
            'ICON'   => 'delete',
            'TEXT'   => Loc::getMessage('uplab.tilda_MENU_CLEAR_CACHE'),
            'ACTION' => "if(confirm('" . CUtil::JSEscape(Loc::getMessage('uplab.tilda_MENU_CLEAR_CACHE_COFIRM')) . "')) " . $lAdmin->ActionDoGroup($itemId, 'delete')
        ];
    }

    $row->AddActions($arActions);
}

/*
 * Групповые действия
 */
$ar = [
    "delete"     => true,
    "for_all"    => true
];
//for Intranet editions: structure group operations and last authorization time
$arParams = ["select_onchange" => "document.getElementById('bx_user_groups').style.display = (this.value == 'add_group' || this.value == 'remove_group'? 'block':'none');" . (isset($ar["structure"]) ? "document.getElementById('bx_user_structure').style.display = (this.value == 'add_structure' || this.value == 'remove_structure'? 'block':'none');" : "")];
$lAdmin->AddGroupActionTable($ar, $arParams);


$lAdmin->AddAdminContextMenu();

$lAdmin->CheckListMode();

require($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/include/prolog_admin_after.php");

$lAdmin->DisplayFilter($filterFields);
$lAdmin->DisplayList();

require($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/include/epilog_admin.php");