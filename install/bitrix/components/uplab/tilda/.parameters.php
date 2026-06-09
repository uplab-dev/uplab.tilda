<?php

use Bitrix\Main\Loader;
use Uplab\Tilda\Common;
use Uplab\Tilda\Enum\MoveResourcesTarget;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
/** @var array $arCurrentValues */


if (!Loader::includeModule('uplab.tilda')) {
    ShowError(GetMessage('CC_UPT_NOT_INSTALLED'));
    return false;
}

$projectsList = Common::getAssocProjectsList();

$currentProject = !empty($arCurrentValues['PROJECT']) ? $arCurrentValues['PROJECT'] : key($projectsList);

if (!$projectsList || !$currentProject) {
    echo "<script>alert('" . GetMessage("UPT_NO_PROJECTS") . "');</script>";
    return false;
}

// Build the resource-move list from the single source of truth (enum).
$moveResourcesValues = array();
foreach (MoveResourcesTarget::langSuffixes() as $optValue => $langSuffix) {
    $moveResourcesValues[$optValue] = GetMessage('UPT_' . $langSuffix);
}

$arComponentParameters = array(
    'GROUPS'     => array(),
    'PARAMETERS' => array(
        'PROJECT'           => array(
            'NAME'    => GetMessage('UPT_SELECT_PROJECT'),
            'TYPE'    => 'LIST',
            'DEFAULT' => '',
            'PARENT'  => 'BASE',
            'REFRESH' => 'Y',
            'VALUES'  => $projectsList
        ),
        'PAGE'              => array(
            'NAME'    => GetMessage('UPT_SELECT_PAGE'),
            'TYPE'    => 'LIST',
            'DEFAULT' => '',
            'PARENT'  => 'BASE',
            'REFRESH' => 'Y',
            'VALUES'  => Common::getAssocPagesList($currentProject),
        ),
        'STOP_CACHE'        => array(
            'NAME'    => GetMessage('UPT_STOP_CACHE'),
            'TYPE'    => 'CHECKBOX',
            'DEFAULT' => 'N',
            'PARENT'  => 'BASE',
            'VALUES'  => ''
        ),
        'MOVE_RESOURCES_TO' => array(
            'NAME'    => GetMessage('UPT_MOVE_TILDA_ASSETS'),
            'TYPE'    => 'LIST',
            'DEFAULT' => '',
            'PARENT'  => 'BASE',
            'REFRESH' => 'Y',
            'VALUES'  => $moveResourcesValues,
        ),
    ),
);