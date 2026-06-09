<?php

namespace Uplab\Tilda;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;

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
    /**
     * Выполняет GET-запрос по указанному URL.
     *
     * Использует cURL, если расширение доступно (с таймаутами из настроек
     * модуля `UPT_CURLOPT_CONNECTTIMEOUT`/`UPT_CURLOPT_TIMEOUT`), иначе
     * `file_get_contents`. Ошибки транспорта логируются через
     * {@see Helper::notifyError()}.
     *
     * @param string $url Полный URL запроса.
     * @return string|false Тело ответа либо false при ошибке.
     */
    public static function makeRequest($url)
    {
        $connectTimeout = (int)Option::get("uplab.tilda", "UPT_CURLOPT_CONNECTTIMEOUT", 15);
        $timeout = (int)Option::get("uplab.tilda", "UPT_CURLOPT_TIMEOUT", 15);

        if (function_exists('curl_init')) {
            $options = [
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_POST           => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36',
                CURLOPT_COOKIEFILE     => $_SERVER['DOCUMENT_ROOT'] . '/upload/tilda_cookie.txt',
                CURLOPT_COOKIEJAR      => $_SERVER['DOCUMENT_ROOT'] . '/upload/tilda_cookie.txt',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_AUTOREFERER    => true,
                CURLOPT_CONNECTTIMEOUT => ($connectTimeout >= 0) ? $connectTimeout : 15,
                CURLOPT_TIMEOUT        => $timeout > 1 ? $timeout : 15,
                CURLOPT_MAXREDIRS      => 10,
            ];

            $curl = curl_init($url);
            curl_setopt_array($curl, $options);

            $content = curl_exec($curl);

            if ($errorNumber = curl_errno($curl)) {
                $errorMsg = curl_error($curl);
                $errorMsg2 = curl_strerror($errorNumber);

                Helper::notifyError(Loc::getMessage('uplab.tilda_ERROR_CURL') . $errorMsg . ' (' . $errorMsg2 . ') (' . $errorNumber . ')');

                $content = false;
            }

            curl_close($curl);
        } else {
            $content = file_get_contents($url);

            if ($content === false) {
                Helper::notifyError(Loc::getMessage('uplab.tilda_ERROR_FGC'));
            }
        }

        return $content;
    }

    /**
     * Формирует URL запроса к Tilda API и возвращает закэшированный результат.
     *
     * Метод проверяется по allowlist (`getprojectslist`, `getpageslist`,
     * `getpagefull`); ключи API и параметры экранируются через
     * `rawurlencode()`. Для запросов отдельных страниц (`getpagefull` и т.п.)
     * кэш складывается в каталог по хэшу URL и регистрируется в таблице
     * {@see CacheTable}; списки кэшируются по имени метода.
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

        $allowedMethods = ['getprojectslist', 'getpageslist', 'getpagefull'];

        if (!in_array($method, $allowedMethods, true)) {
            return false;
        }

        Common::getOptions();

        $paramsStr = '';

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $paramsStr .= '&' . rawurlencode($key) . '=' . rawurlencode($value);
            }
        }

        $url = 'https://api.tildacdn.info/v1/' . rawurlencode($method) . '/?publickey=' . rawurlencode(Common::$publickey) . '&secretkey=' . rawurlencode(Common::$secretkey) . $paramsStr;

        $cacheId = md5($url);
        $cacheDir = "/$method/";
        $noteInBase = false;

        if (
            $method !== 'getprojectslist' &&
            $method !== 'getpageslist'
        ) {
            $cacheDir = "/$cacheId/";
            $noteInBase = true;
        }

        return Cache::cache($url, $cacheId, $cacheDir, $noteInBase);
    }

}
