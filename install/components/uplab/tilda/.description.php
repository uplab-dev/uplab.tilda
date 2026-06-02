<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = array(
    'NAME'        => GetMessage('NAME'),
    'DESCRIPTION' => GetMessage('DESCRIPTION'),
    'ICON'        => '/images/.gif',
    'PATH'        => array(
        'ID'   => 'uplab',
        'NAME' => GetMessage('PATH_NAME')
    ),
);