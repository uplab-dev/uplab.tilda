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

namespace Uplab\Tilda;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Uplab\Tilda\Diag\Logger;
use Uplab\Tilda\Enum\ApiMethod;
use Uplab\Tilda\Service\Cache;

/**
 * HTTP-клиент для обращений к Tilda API.
 *
 * Выполняет запросы через cURL (с откатом на `file_get_contents`), проверяет
 * метод по allowlist, экранирует параметры через `rawurlencode()` и
 * делегирует кэширование ответа классу {@see Cache}.
 *
 * @package Uplab\Tilda
 */
class Request
{
    /** User-Agent, с которым модуль обращается к Tilda API (обе транспортные ветки). */
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36';

    /**
     * Возвращает базовый URL Tilda API из настроек модуля.
     *
     * Адрес намеренно не ограничивается ни доменом, ни формой записи сверх
     * необходимого: настройка `UPT_API_URL` существует ровно для того, чтобы
     * адрес API можно было поменять на месте, не дожидаясь обновления модуля.
     * Проверяется только протокол HTTPS и наличие хоста. Некорректное значение
     * заменяется на {@see Common::DEFAULT_API_URL}.
     *
     * @return string
     */
    public static function resolveApiUrl()
    {
        $apiUrl = trim((string)Option::get('uplab.tilda', 'UPT_API_URL', Common::DEFAULT_API_URL));

        if (self::isValidApiUrl($apiUrl)) {
            if (strcasecmp($apiUrl, Common::DEFAULT_API_URL) !== 0) {
                Logger::debug('Custom Tilda API URL is used', ['url' => Logger::maskUrl($apiUrl)]);
            }

            Helper::clearNotifyOnce('API_URL');

            return $apiUrl;
        }

        if ($apiUrl !== '') {
            Logger::warning('API URL rejected, falling back to default', ['url' => Logger::maskUrl($apiUrl)]);
            Helper::notifyOnce(
                'API_URL',
                Loc::getMessage('uplab.tilda_API_URL_INVALID', ['#URL#' => Common::DEFAULT_API_URL])
            );
        }

        return Common::DEFAULT_API_URL;
    }

    /**
     * Проверяет форму адреса API: HTTPS и наличие хоста.
     *
     * @param string $url
     * @return bool
     */
    private static function isValidApiUrl($url)
    {
        if (strncmp($url, 'https://', 8) !== 0) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts) && !empty($parts['host']);
    }

    /**
     * Строит URL запроса к Tilda API.
     *
     * @param string $apiUrl    Базовый URL API (должен начинаться с https://).
     * @param string $method    Метод API (например, getprojectslist).
     * @param string $publicKey Публичный ключ.
     * @param string $secretKey Секретный ключ.
     * @param array  $params    Дополнительные GET-параметры.
     * @return string
     */
    private static function buildUrl($apiUrl, $method, $publicKey, $secretKey, array $params = [])
    {
        $query = '';
        foreach ($params as $key => $value) {
            $query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }

        return rtrim($apiUrl, '/') . '/' . rawurlencode($method)
            . '/?publickey=' . rawurlencode($publicKey)
            . '&secretkey=' . rawurlencode($secretKey)
            . $query;
    }

    /**
     * Выполняет HTTPS GET-запрос по указанному URL.
     *
     * Использует cURL, если расширение доступно; ограничивает протоколы
     * исключительно HTTPS и явно проверяет SSL-сертификат. В качестве
     * fallback применяет `file_get_contents` с аналогичной проверкой протокола.
     * Ошибки транспорта логируются через {@see Helper::notifyError()}.
     *
     * @param string $url Полный HTTPS URL запроса.
     * @return string|false Тело ответа либо false при ошибке.
     */
    public static function makeRequest($url)
    {
        // Ensure only HTTPS is allowed regardless of what is stored in settings
        if (strncmp($url, 'https://', 8) !== 0) {
            Helper::notifyError(Loc::getMessage('uplab.tilda_ERROR_PROTOCOL'));
            Logger::error('Blocked non-HTTPS request', ['url' => Logger::maskUrl($url)]);
            return false;
        }

        $connectTimeout = (int)Option::get('uplab.tilda', 'UPT_CURLOPT_CONNECTTIMEOUT', 15);
        $timeout        = (int)Option::get('uplab.tilda', 'UPT_CURLOPT_TIMEOUT', 15);

        Logger::debug('HTTP request', ['url' => Logger::maskUrl($url)]);

        $startedAt = microtime(true);

        if (function_exists('curl_init')) {
            $options = [
                CURLOPT_CUSTOMREQUEST   => 'GET',
                CURLOPT_POST            => false,
                CURLOPT_USERAGENT       => self::USER_AGENT,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_HEADER          => false,
                CURLOPT_FOLLOWLOCATION  => true,
                CURLOPT_MAXREDIRS       => 3,
                // Enforce HTTPS only — prevents SSRF via protocol downgrade
                CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                // Explicit SSL verification
                CURLOPT_SSL_VERIFYPEER  => true,
                CURLOPT_SSL_VERIFYHOST  => 2,
                CURLOPT_ENCODING        => '',
                CURLOPT_CONNECTTIMEOUT  => ($connectTimeout >= 0) ? $connectTimeout : 15,
                CURLOPT_TIMEOUT         => $timeout > 1 ? $timeout : 15,
            ];

            $curl = curl_init($url);
            curl_setopt_array($curl, $options);

            $content = curl_exec($curl);

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($errorNumber = curl_errno($curl)) {
                $errorMsg  = curl_error($curl);
                $errorMsg2 = curl_strerror($errorNumber);

                Helper::notifyError(Loc::getMessage('uplab.tilda_ERROR_CURL') . $errorMsg . ' (' . $errorMsg2 . ') (' . $errorNumber . ')');
                Logger::error('cURL request failed', [
                    'errno' => $errorNumber,
                    'error' => $errorMsg . ' (' . $errorMsg2 . ')',
                    'url'   => Logger::maskUrl($url),
                ]);

                $content = false;
            } else {
                Logger::debug('HTTP response received', [
                    'http_code'   => $httpCode,
                    'bytes'       => strlen((string)$content),
                    'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                    'body'        => $content,
                ]);
            }

            curl_close($curl);
        } else {
            // Без cURL политику безопасности задаёт контекст потока. Ключевое —
            // follow_location = 0: по умолчанию обёртка следует редиректам, и
            // ответ «302 Location: http://…» увёл бы ключи API на открытый канал.
            // В cURL-ветке это запрещено через CURLOPT_REDIR_PROTOCOLS, здесь
            // редиректы не следуем вовсе. Проверку сертификата включаем явно.
            // Опции HTTPS задаются в секции http (это та же HTTP-обёртка), TLS — в ssl.
            $context = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'follow_location' => 0,
                    // Без ignore_errors обёртка возвращает false на 4xx/5xx, и текст
                    // ошибки Tilda теряется. cURL-ветка тело отдаёт при любом коде —
                    // выравниваем поведение, иначе вместо «Wrong publickey length»
                    // администратор увидит безликое «нет ответа от Tilda».
                    'ignore_errors'   => true,
                    'timeout'         => $timeout > 1 ? $timeout : 15,
                    'user_agent'      => self::USER_AGENT,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $content = @file_get_contents($url, false, $context);

            $httpCode = 0;
            if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $statusMatch)) {
                $httpCode = (int)$statusMatch[1];
            }

            if ($content === false) {
                Helper::notifyError(Loc::getMessage('uplab.tilda_ERROR_FGC'));
                Logger::error('file_get_contents failed', [
                    'http_code' => $httpCode,
                    'url'       => Logger::maskUrl($url),
                ]);
            } else {
                if ($httpCode >= 300 && $httpCode < 400) {
                    // Ответ-редирект отдаётся как есть: разбор JSON ниже не пройдёт,
                    // поэтому явно объясняем причину в журнале.
                    Logger::warning('Redirect not followed (fallback transport allows HTTPS target only)', [
                        'http_code' => $httpCode,
                        'url'       => Logger::maskUrl($url),
                    ]);
                }

                Logger::debug('HTTP response received (file_get_contents)', [
                    'http_code'   => $httpCode,
                    'bytes'       => strlen($content),
                    'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                    'body'        => $content,
                ]);
            }
        }

        return $content;
    }

    /**
     * Проверяет подключение к Tilda API с указанными ключами.
     *
     * Запрашивает `getprojectslist` без кэша — только чтобы убедиться,
     * что ключи валидны и API отвечает корректно.
     *
     * @param string $publicKey Публичный ключ API.
     * @param string $secretKey Секретный ключ API.
     * @return array|false Поле `result` ответа Tilda при успехе, false при ошибке транспорта или невалидном ответе.
     */
    public static function checkConnection($publicKey, $secretKey)
    {
        $url  = static::buildUrl(static::resolveApiUrl(), ApiMethod::PROJECTS_LIST, $publicKey, $secretKey);
        $body = static::makeRequest($url);

        if ($body === false) {
            return false;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status'])) {
            Logger::error('Invalid API response on checkConnection', [
                'json_error' => json_last_error_msg(),
                'body'       => $body,
            ]);
            return false;
        }

        return $data;
    }

    /**
     * Формирует URL запроса к Tilda API и возвращает закэшированный результат.
     *
     * Метод проверяется по allowlist ({@see ApiMethod}); ключи API и параметры экранируются через
     * `rawurlencode()`. Для запросов отдельных страниц (`getpagefull` и т.п.)
     * кэш складывается в каталог по хэшу URL и регистрируется в таблице
     * {@see \Uplab\Tilda\Model\CacheTable}; списки кэшируются по имени метода.
     *
     * @param string|null $method Метод API из allowlist.
     * @param array       $params Дополнительные GET-параметры запроса.
     * @return array|false Поле `result` ответа Tilda, пустой массив или false при недопустимом методе.
     */
    public static function getData($method = null, $params = [])
    {
        if ($method === null) {
            return false;
        }

        if (!ApiMethod::isValid($method)) {
            return false;
        }

        Common::getOptions();

        Logger::debug('API getData', ['method' => $method, 'params' => $params]);

        $url = static::buildUrl(Common::$apiUrl, $method, Common::$publickey, Common::$secretkey, $params);

        $cacheId    = md5($url);
        $cacheDir   = "/$method/";
        $noteInBase = false;

        if ($method !== ApiMethod::PROJECTS_LIST && $method !== ApiMethod::PAGES_LIST) {
            $cacheDir   = "/$cacheId/";
            $noteInBase = true;
        }

        return Cache::cache($url, $cacheId, $cacheDir, $noteInBase);
    }
}
