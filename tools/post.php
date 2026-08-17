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

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\Response\Json;
use Uplab\Tilda\Diag\Logger;
use Uplab\Tilda\Request;
use Uplab\Tilda\Service\Cache;

const STOP_STATISTICS = true;
const NO_AGENT_CHECK = true;
const DisableEventsCheck = true;
const BX_SECURITY_SHOW_MESSAGE = true;
const PUBLIC_AJAX_MODE = true;
const NOT_CHECK_PERMISSIONS = true;
const ADMIN_MODULE_NAME = 'uplab.tilda';

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

global $APPLICATION;
global $USER;

$status = 'error';
$message = '';

if (!Loader::includeModule(ADMIN_MODULE_NAME)) {
    $message = Loc::getMessage('uplab.tilda_NO_MODULE');
} elseif (!$USER->IsAuthorized() || $APPLICATION->GetGroupRight(ADMIN_MODULE_NAME) < 'W') {
    $message = Loc::getMessage('ACCESS_DENIED');
} elseif (!check_bitrix_sessid()) {
    $message = Loc::getMessage('uplab.tilda_SESSION_EXPIRED');
} else {
    $request = Context::getCurrent()->getRequest();

    if ($request->get('clearCache') === 'Y') {
        Cache::clearAllCache();
        $status  = 'success';
        $message = Loc::getMessage('uplab.tilda_CACHE_CLEARED');

    } elseif ($request->get('clearCacheList') === 'Y') {
        Cache::clearListCache();
        $status  = 'success';
        $message = Loc::getMessage('uplab.tilda_CACHE_LIST_CLEARED');

    } elseif ($request->get('clearLogs') === 'Y') {
        $count   = Logger::clearLogs();
        $status  = 'success';
        $message = Loc::getMessage('uplab.tilda_LOGS_CLEARED', ['#COUNT#' => $count]);

        // Чужие файлы намеренно не удаляем — вместо этого показываем их админу,
        // чтобы кнопка «Очистить логи» не сносила то, что положили не мы.
        $foreignFiles = Logger::findForeignFiles();
        if ($foreignFiles) {
            $message .= ' ' . Loc::getMessage('uplab.tilda_LOGS_CLEARED_FOREIGN', [
                '#FILES#' => implode(', ', $foreignFiles),
            ]);
        }

    } elseif ($request->get('checkConnection') === 'Y') {
        $publicKey = trim((string)$request->getPost('publicKey'));
        $secretKey = trim((string)$request->getPost('secretKey'));

        if ($publicKey === '' || $secretKey === '') {
            $message = Loc::getMessage('uplab.tilda_CHECK_CONN_EMPTY_KEYS');
        } else {
            $data = Request::checkConnection($publicKey, $secretKey);

            if ($data === false) {
                $message = Loc::getMessage('uplab.tilda_CHECK_CONN_CURL_ERROR');
            } elseif ($data['status'] === 'FOUND') {
                $count   = isset($data['result']) && is_array($data['result']) ? count($data['result']) : 0;
                $status  = 'success';
                $message = Loc::getMessage('uplab.tilda_CHECK_CONN_SUCCESS', ['#COUNT#' => $count]);
            } else {
                $apiMsg  = isset($data['message']) ? ': ' . $data['message'] : '';
                $message = Loc::getMessage('uplab.tilda_CHECK_CONN_API_ERROR') . $apiMsg;
            }
        }

    } else {
        $message = Loc::getMessage('uplab.tilda_UNKNOWN_ACTION');
    }
}

// Чистим возможный буфер вывода и отдаём JSON через штатный Response-класс ядра
// (\Bitrix\Main\Engine\Response\Json сам выставляет Content-Type: application/json).
$APPLICATION->RestartBuffer();

Application::getInstance()->end(0, new Json([
    'status'  => $status,
    'message' => (string)$message,
]));