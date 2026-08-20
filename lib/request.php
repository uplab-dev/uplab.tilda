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
     * Число попыток запроса по умолчанию.
     *
     * У Tilda наблюдается обрыв отдачи тяжёлых страниц: приходит около 90%
     * ответа, дальше сервер молчит, не закрывая соединение. На следующей
     * попытке тот же запрос обычно проходит целиком, поэтому одна неудача
     * не должна оставлять страницу сайта без контента.
     */
    const DEFAULT_ATTEMPTS = 3;

    /** Предел числа попыток: больше — только задержка ответа сайта посетителю. */
    const MAX_ATTEMPTS = 5;

    /**
     * Секунды без новых байт, после которых начавшаяся передача считается
     * зависшей; 0 — не проверять.
     *
     * Проверяется только передача, которая уже пошла: ожидание первого байта —
     * это нормальная генерация страницы на стороне Tilda, обрывать её нельзя.
     */
    const DEFAULT_STALL_TIMEOUT = 5;

    /** Пауза между попытками, микросекунды. */
    const RETRY_DELAY_US = 250000;

    /**
     * Коды cURL, при которых повтор запроса имеет смысл: обрыв, зависание или
     * неудача соединения, а не отказ по существу.
     *
     * 6 — не разрешилось имя хоста, 7 — не удалось подключиться, 18 — тело
     * пришло не целиком, 28 — таймаут, 35 — сбой TLS-рукопожатия, 42 — запрос
     * прерван детектором стопора (см. {@see self::DEFAULT_STALL_TIMEOUT}),
     * 52 — пустой ответ, 55 — ошибка отправки, 56 — ошибка чтения.
     */
    const RETRYABLE_CURL_ERRORS = [6, 7, 18, 28, 35, 42, 52, 55, 56];

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
            Logger::warning(Loc::getMessage('uplab.tilda_LOG_API_URL_REJECTED'), ['url' => Logger::maskUrl($apiUrl)]);
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
     * Кратко описывает запрос к API для сообщений об ошибке: метод и
     * идентификаторы проекта/страницы, без ключей доступа.
     *
     * Нужно, чтобы по записи в журнале событий было понятно, какая именно
     * страница Tilda не загрузилась: одного URI страницы сайта мало, когда на
     * сайте несколько тегов.
     *
     * @param string $url Полный URL запроса к Tilda API.
     * @return string Например, `getpagefull, pageid=123456`.
     */
    public static function describeRequest($url)
    {
        $parts  = parse_url((string)$url);
        $method = !empty($parts['path']) ? basename($parts['path']) : '';

        $description = ($method !== '') ? $method : 'tilda api';

        if (!empty($parts['query'])) {
            $query = [];
            parse_str($parts['query'], $query);

            foreach (['projectid', 'pageid'] as $key) {
                if (!empty($query[$key])) {
                    $description .= ', ' . $key . '=' . (int)$query[$key];
                }
            }
        }

        return $description;
    }

    /**
     * Выполняет HTTPS GET-запрос по указанному URL, при обрыве передачи
     * повторяя попытку.
     *
     * Использует cURL, если расширение доступно; ограничивает протоколы
     * исключительно HTTPS и явно проверяет SSL-сертификат. В качестве
     * fallback применяет `file_get_contents` с аналогичной проверкой протокола.
     * Число попыток задаётся опцией `UPT_REQUEST_ATTEMPTS`, повтор выполняется
     * только для кодов из {@see self::RETRYABLE_CURL_ERRORS}. Уведомление
     * администратору и запись об ошибке уходят один раз, когда исчерпаны все
     * попытки, — иначе один сбой давал бы три сообщения об одном и том же.
     *
     * @param string $url Полный HTTPS URL запроса.
     * @return string|false Тело ответа либо false при ошибке.
     */
    public static function makeRequest($url)
    {
        // Ensure only HTTPS is allowed regardless of what is stored in settings
        if (strncmp($url, 'https://', 8) !== 0) {
            Helper::notifyError(Loc::getMessage('uplab.tilda_ERROR_PROTOCOL'));
            Logger::error(Loc::getMessage('uplab.tilda_LOG_NON_HTTPS_BLOCKED'), ['url' => Logger::maskUrl($url)]);
            return false;
        }

        Logger::debug('HTTP request', ['url' => Logger::maskUrl($url)]);

        $attempts = self::resolveAttempts();
        $useCurl  = function_exists('curl_init');
        $failure  = null;
        $made     = 0;

        // UPT_CURLOPT_TIMEOUT remains the upper bound for the whole logical
        // request, not for every individual attempt. Otherwise three attempts
        // turn the historical 15-second limit into 45 seconds and can exhaust
        // web workers during an upstream outage.
        $deadline = microtime(true) + self::resolveTimeout();

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                break;
            }

            $made   = $attempt;
            $result = $useCurl
                ? self::attemptCurl($url, $remaining)
                : self::attemptStream($url, $remaining);

            if ($result['content'] !== false) {
                return $result['content'];
            }

            $failure = $result;

            if (!$result['retryable'] || $attempt >= $attempts) {
                break;
            }

            $remainingUs = (int)floor(($deadline - microtime(true)) * 1000000);
            if ($remainingUs <= self::RETRY_DELAY_US) {
                break;
            }

            Logger::warning(Loc::getMessage('uplab.tilda_LOG_RETRY'), array_merge(
                ['attempt' => $attempt . '/' . $attempts],
                $result['context']
            ));

            usleep(self::RETRY_DELAY_US);
        }

        if (is_array($failure)) {
            self::reportFailure($failure, $made, $attempts);
        }

        return false;
    }

    /**
     * Сообщает об окончательной неудаче запроса: уведомление в админке плюс
     * запись в журнал модуля.
     *
     * @param array $failure  Описание последней неудачной попытки.
     * @param int   $made     Сколько попыток выполнено.
     * @param int   $attempts Сколько попыток было разрешено.
     * @return void
     */
    private static function reportFailure(array $failure, $made, $attempts)
    {
        $suffix = ($attempts > 1)
            ? ' ' . Loc::getMessage('uplab.tilda_ERROR_ATTEMPTS', ['#COUNT#' => $made])
            : '';

        Helper::notifyError($failure['message'] . $suffix);

        Logger::error($failure['logMessage'], array_merge(
            ['attempts' => $made . '/' . $attempts],
            $failure['context']
        ));
    }

    /**
     * Одна попытка запроса через cURL.
     *
     * @param string $url              Полный HTTPS URL запроса.
     * @param float  $remainingSeconds Оставшийся общий бюджет запроса, секунд.
     * @return array Ключи: `content` (тело или false), `retryable`, `message`
     *               (текст для уведомления), `logMessage`, `context` (данные
     *               для журнала).
     */
    private static function attemptCurl($url, $remainingSeconds)
    {
        $connectTimeout = (int)Option::get('uplab.tilda', 'UPT_CURLOPT_CONNECTTIMEOUT', 15);
        $remainingMs    = max(1, (int)floor($remainingSeconds * 1000));
        $connectMs      = ($connectTimeout >= 0) ? $connectTimeout * 1000 : 15000;

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
            CURLOPT_CONNECTTIMEOUT_MS => min($connectMs, $remainingMs),
            CURLOPT_TIMEOUT_MS        => $remainingMs,
        ];

        // Детектор стопора. Без него зависшая передача занимает весь
        // CURLOPT_TIMEOUT, и на повтор в пределах разумного времени ответа
        // страницы места не остаётся. Ожидание первого байта не трогаем:
        // холодная генерация страницы в Tilda — это не сбой.
        $stallTimeout   = self::resolveStallTimeout();
        $stalled        = false;
        $lastProgressAt = microtime(true);
        $lastDownloaded = 0.0;

        if ($stallTimeout > 0) {
            $options[CURLOPT_NOPROGRESS]       = false;
            $options[CURLOPT_PROGRESSFUNCTION] = function ($curl, $downloadTotal, $downloaded, $uploadTotal, $uploaded) use (
                &$stalled,
                &$lastProgressAt,
                &$lastDownloaded,
                $stallTimeout
            ) {
                $now = microtime(true);

                if ($downloaded > $lastDownloaded) {
                    $lastDownloaded = $downloaded;
                    $lastProgressAt = $now;

                    return 0;
                }

                if ($downloaded > 0 && ($now - $lastProgressAt) > $stallTimeout) {
                    $stalled = true;

                    // Ненулевой возврат прерывает передачу с кодом 42.
                    return 1;
                }

                return 0;
            };
        }

        $startedAt = microtime(true);

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);

        $content = curl_exec($curl);

        $errorNumber = curl_errno($curl);
        $httpCode    = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($errorNumber) {
            $errorMsg  = curl_error($curl);
            $errorMsg2 = curl_strerror($errorNumber);

            // Сколько успело прийти до обрыва — ключевая величина при
            // разборе таймаутов: одинаковый размер от попытки к попытке
            // означает, что поток встаёт на одном и том же месте, а не что
            // сеть работает нестабильно.
            $bytes    = (int)curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);
            $duration = (int)round(curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000);
            $request  = self::describeRequest($url);

            curl_close($curl);

            $error = $stalled
                ? Loc::getMessage('uplab.tilda_ERROR_STALLED', ['#SECONDS#' => $stallTimeout])
                : $errorMsg . ' (' . $errorMsg2 . ') (' . $errorNumber . ')';

            return [
                'content'    => false,
                'retryable'  => in_array($errorNumber, self::RETRYABLE_CURL_ERRORS, true),
                'message'    => Loc::getMessage('uplab.tilda_ERROR_CURL') . $error
                    . ' [' . $request . ', ' . $duration . ' ms, ' . $bytes . ' bytes]',
                'logMessage' => $stalled
                    ? Loc::getMessage('uplab.tilda_LOG_STALLED')
                    : Loc::getMessage('uplab.tilda_LOG_CURL_FAILED'),
                'context'    => [
                    'request'     => $request,
                    'errno'       => $errorNumber,
                    'error'       => $error,
                    'http_code'   => $httpCode,
                    'duration_ms' => $duration,
                    'bytes'       => $bytes,
                    'url'         => Logger::maskUrl($url),
                ],
            ];
        }

        Logger::debug('HTTP response received', [
            'request'     => self::describeRequest($url),
            'http_code'   => $httpCode,
            'bytes'       => strlen((string)$content),
            'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            'body'        => $content,
        ]);

        curl_close($curl);

        return self::success($content);
    }

    /**
     * Одна попытка запроса через `file_get_contents` — используется, когда в PHP
     * нет cURL.
     *
     * Без cURL политику безопасности задаёт контекст потока. Ключевое —
     * follow_location = 0: по умолчанию обёртка следует редиректам, и
     * ответ «302 Location: http://…» увёл бы ключи API на открытый канал.
     * В cURL-ветке это запрещено через CURLOPT_REDIR_PROTOCOLS, здесь
     * редиректы не следуем вовсе. Проверку сертификата включаем явно.
     * Опции HTTPS задаются в секции http (это та же HTTP-обёртка), TLS — в ssl.
     *
     * @param string $url              Полный HTTPS URL запроса.
     * @param float  $remainingSeconds Оставшийся общий бюджет запроса, секунд.
     * @return array Структура та же, что у {@see self::attemptCurl()}.
     */
    private static function attemptStream($url, $remainingSeconds)
    {
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'follow_location' => 0,
                // Без ignore_errors обёртка возвращает false на 4xx/5xx, и текст
                // ошибки Tilda теряется. cURL-ветка тело отдаёт при любом коде —
                // выравниваем поведение, иначе вместо «Wrong publickey length»
                // администратор увидит безликое «нет ответа от Tilda».
                'ignore_errors'   => true,
                'timeout'         => max(0.001, (float)$remainingSeconds),
                'user_agent'      => self::USER_AGENT,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $startedAt = microtime(true);

        $content = @file_get_contents($url, false, $context);

        $httpCode = 0;
        if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $statusMatch)) {
            $httpCode = (int)$statusMatch[1];
        }

        $duration = (int)round((microtime(true) - $startedAt) * 1000);

        if ($content === false) {
            $request = self::describeRequest($url);

            return [
                'content'    => false,
                // Обрыв соединения обёртка от отказа по существу не отличает,
                // а тело 4xx/5xx она отдаёт (ignore_errors) — значит false
                // здесь всегда транспорт, и повтор оправдан.
                'retryable'  => true,
                'message'    => Loc::getMessage('uplab.tilda_ERROR_FGC') . ' [' . $request . ', ' . $duration . ' ms]',
                'logMessage' => Loc::getMessage('uplab.tilda_LOG_FGC_FAILED'),
                'context'    => [
                    'request'     => $request,
                    'http_code'   => $httpCode,
                    'duration_ms' => $duration,
                    'url'         => Logger::maskUrl($url),
                ],
            ];
        }

        if ($httpCode >= 300 && $httpCode < 400) {
            // Ответ-редирект отдаётся как есть: разбор JSON ниже не пройдёт,
            // поэтому явно объясняем причину в журнале.
            Logger::warning(Loc::getMessage('uplab.tilda_LOG_REDIRECT_NOT_FOLLOWED'), [
                'http_code' => $httpCode,
                'url'       => Logger::maskUrl($url),
            ]);
        }

        Logger::debug('HTTP response received (file_get_contents)', [
            'http_code'   => $httpCode,
            'bytes'       => strlen($content),
            'duration_ms' => $duration,
            'body'        => $content,
        ]);

        return self::success($content);
    }

    /**
     * Формирует результат удачной попытки в том же виде, что и неудачной.
     *
     * @param string $content Тело ответа.
     * @return array
     */
    private static function success($content)
    {
        return [
            'content'    => $content,
            'retryable'  => false,
            'message'    => '',
            'logMessage' => '',
            'context'    => [],
        ];
    }

    /**
     * Возвращает число попыток запроса из настроек модуля.
     *
     * @return int От 1 до {@see self::MAX_ATTEMPTS}.
     */
    private static function resolveAttempts()
    {
        $attempts = (int)Option::get('uplab.tilda', 'UPT_REQUEST_ATTEMPTS', self::DEFAULT_ATTEMPTS);

        if ($attempts < 1) {
            return 1;
        }

        return ($attempts > self::MAX_ATTEMPTS) ? self::MAX_ATTEMPTS : $attempts;
    }

    /**
     * Возвращает общий бюджет логического запроса вместе со всеми повторами.
     *
     * Сохраняет прежний смысл UPT_CURLOPT_TIMEOUT: это максимальное время,
     * которое один вызов к Tilda может занимать PHP-worker, а не лимит каждой
     * попытки по отдельности.
     *
     * @return int Секунды, не меньше 1.
     */
    private static function resolveTimeout()
    {
        $timeout = (int)Option::get('uplab.tilda', 'UPT_CURLOPT_TIMEOUT', 15);

        return ($timeout >= 1) ? $timeout : 15;
    }

    /**
     * Возвращает порог детектора стопора из настроек модуля.
     *
     * @return int Секунды; 0 — проверка отключена.
     */
    private static function resolveStallTimeout()
    {
        $seconds = (int)Option::get('uplab.tilda', 'UPT_STALL_TIMEOUT', self::DEFAULT_STALL_TIMEOUT);

        return ($seconds > 0) ? $seconds : 0;
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
            Logger::error(Loc::getMessage('uplab.tilda_LOG_CHECK_CONN_INVALID'), [
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
        $ttl        = self::resolveListCacheTtl();

        if ($method !== ApiMethod::PROJECTS_LIST && $method !== ApiMethod::PAGES_LIST) {
            $cacheDir   = "/$cacheId/";
            $noteInBase = true;
            $ttl        = Cache::DEFAULT_TTL;
        }

        return Cache::cache($url, $cacheId, $cacheDir, $noteInBase, $ttl);
    }

    /**
     * Возвращает срок жизни кэша списков проектов и страниц.
     *
     * Списки кэшируются отдельно от контента и ненадолго: со сроком контента
     * (неделя) страница, только что созданная в Tilda, не появляется в
     * редакторе до ручной очистки кэша.
     *
     * @return int Секунды; при некорректной настройке — {@see Cache::DEFAULT_LIST_TTL}.
     */
    private static function resolveListCacheTtl()
    {
        $ttl = (int)Option::get('uplab.tilda', 'UPT_LIST_CACHE_TTL', Cache::DEFAULT_LIST_TTL);

        return ($ttl > 0) ? $ttl : Cache::DEFAULT_LIST_TTL;
    }
}
