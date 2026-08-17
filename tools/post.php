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
use Uplab\Tilda\Common;
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

// Дополнительные поля ответа конкретного действия (например, список страниц).
$payload = [];

// Действие → минимальное право на модуль. Чтение списка страниц нужно
// редактору и требует только права на просмотр; всё, что меняет состояние
// или обращается к API с произвольными ключами, — права на изменение.
$actionRights = [
    'clearCache'      => 'W',
    'clearCacheList'  => 'W',
    'clearLogs'       => 'W',
    'checkConnection' => 'W',
    'getPages'        => 'R',
];

if (!Loader::includeModule(ADMIN_MODULE_NAME)) {
    $message = Loc::getMessage('uplab.tilda_NO_MODULE');
} else {
    $request = Context::getCurrent()->getRequest();

    $action = '';
    foreach (array_keys($actionRights) as $name) {
        if ($request->get($name) === 'Y') {
            $action = $name;
            break;
        }
    }

    if (!$USER->IsAuthorized()) {
        $message = Loc::getMessage('ACCESS_DENIED');
    } elseif ($action === '') {
        $message = Loc::getMessage('uplab.tilda_UNKNOWN_ACTION');
    } elseif ($APPLICATION->GetGroupRight(ADMIN_MODULE_NAME) < $actionRights[$action]) {
        $message = Loc::getMessage('ACCESS_DENIED');
    } elseif (!check_bitrix_sessid()) {
        $message = Loc::getMessage('uplab.tilda_SESSION_EXPIRED');
    } elseif ($action === 'clearCache') {
        Cache::clearAllCache();
        $status  = 'success';
        $message = Loc::getMessage('uplab.tilda_CACHE_CLEARED');

    } elseif ($action === 'clearCacheList') {
        Cache::clearListCache();
        $status  = 'success';
        $message = Loc::getMessage('uplab.tilda_CACHE_LIST_CLEARED');

    } elseif ($action === 'clearLogs') {
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

    } elseif ($action === 'checkConnection') {
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

    } elseif ($action === 'getPages') {
        $result = Common::getAssocPagesResult((int)$request->get('projectId'));

        if ($result['error'] !== null) {
            // Неудачный запрос и пустой проект — разные состояния: списку
            // страниц в редакторе нельзя показывать «страниц нет», когда
            // Tilda просто не ответила.
            $message = Loc::getMessage('uplab.tilda_PAGES_LOAD_ERROR') . ' ' . $result['error'];
        } else {
            $pages = [];
            foreach ($result['pages'] as $id => $title) {
                $pages[] = ['id' => (int)$id, 'title' => (string)$title];
            }

            $status  = 'success';
            $payload = ['pages' => $pages, 'empty' => empty($pages)];
        }
    }
}

// Чистим возможный буфер вывода и отдаём JSON через штатный Response-класс ядра
// (\Bitrix\Main\Engine\Response\Json сам выставляет Content-Type: application/json).
$APPLICATION->RestartBuffer();

Application::getInstance()->end(0, new Json(array_merge([
    'status'  => $status,
    'message' => (string)$message,
], $payload)));