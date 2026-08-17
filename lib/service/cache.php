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

namespace Uplab\Tilda\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache as BitrixCache;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Uplab\Tilda\Diag\Logger;
use Uplab\Tilda\Helper;
use Uplab\Tilda\Model\CacheTable;
use Uplab\Tilda\Request;

Loc::loadMessages(__FILE__);

/**
 * Сервис кэширования ответов Tilda API поверх {@see \Bitrix\Main\Data\Cache}.
 *
 * Хранит данные в базовом каталоге кэша `cache_tilda` и ведёт реестр
 * закэшированных страниц в таблице {@see CacheTable}. Срок жизни задаётся
 * вызывающим кодом: контент страниц кэшируется надолго, списки проектов и
 * страниц — на короткое время, иначе новая страница Tilda неделю не видна
 * в редакторе. Предоставляет методы точечной и полной очистки кэша.
 *
 * @package Uplab\Tilda\Service
 */
class Cache
{
    /** Срок жизни кэша контента страницы, секунд. */
    const DEFAULT_TTL = 604800;

    /** Срок жизни кэша списков проектов и страниц, секунд. */
    const DEFAULT_LIST_TTL = 600;

    /** @var string Базовый каталог кэша Битрикс для данных Tilda. */
    protected static $cacheBaseDir = 'cache_tilda';

    /**
     * @var string|null Текст ошибки последнего вызова {@see self::cache()}
     *                  либо null, если он завершился успешно.
     *
     * Нужен, чтобы отличать «Tilda вернула пустой список» от «запрос не
     * удался»: сам метод в обоих случаях возвращает пустой массив, а для
     * интерфейса это принципиально разные состояния.
     */
    private static $lastError = null;

    /**
     * Возвращает текст ошибки последнего вызова {@see self::cache()}.
     *
     * @return string|null null, если последний вызов отработал без ошибки.
     */
    public static function getLastError()
    {
        return self::$lastError;
    }

    /**
     * Возвращает данные из кэша либо, при промахе, запрашивает их по URL и
     * кэширует на $ttl секунд.
     *
     * При ответе со статусом `ERROR` кэширование прерывается, сообщение
     * логируется через {@see Helper::notifyError()}, запись в {@see CacheTable}
     * не создаётся и метод возвращает пустой массив. При успешном ответе и
     * $noteInBase = true запись о странице добавляется/обновляется в {@see CacheTable}.
     *
     * @param string   $url        Полный URL запроса к Tilda API.
     * @param string   $cacheId    Идентификатор кэша (обычно md5 от URL).
     * @param string   $cacheDir   Подкаталог кэша.
     * @param bool     $noteInBase Регистрировать ли запись в таблице реестра страниц.
     * @param int|null $ttl        Срок жизни кэша, секунд; null — {@see self::DEFAULT_TTL}.
     * @return array Поле `result` ответа Tilda либо пустой массив.
     */
    public static function cache($url, $cacheId, $cacheDir, $noteInBase = false, $ttl = null)
    {
        $cacheTime = ((int)$ttl > 0) ? (int)$ttl : self::DEFAULT_TTL;

        self::$lastError = null;

        $data = [];

        $cache = BitrixCache::createInstance();

        if ($cache->initCache($cacheTime, $cacheId, $cacheDir, self::$cacheBaseDir)) {
            $data = self::readCachedResponse($cache);

            Logger::debug('Cache hit', [
                'cacheId'  => $cacheId,
                'cacheDir' => $cacheDir,
                'status'   => is_array($data) ? ($data['status'] ?? '') : '',
                'result'   => self::describeResult(is_array($data) ? ($data['result'] ?? null) : null),
            ]);
        } elseif ($cache->startDataCache($cacheTime, $cacheId, $cacheDir, [], self::$cacheBaseDir)) {
            Logger::debug('Cache miss, fetching from API', ['cacheId' => $cacheId, 'url' => Logger::maskUrl($url)]);
            $content = Request::makeRequest($url);

            $data = json_decode((string)$content, true);

            if (!is_array($data) || ($data['status'] ?? '') === 'ERROR') {
                // Описание запроса (метод и id страницы/проекта) идёт в
                // уведомление и журнал: по одному URI страницы сайта непонятно,
                // какая именно страница Tilda не загрузилась.
                $request = Request::describeRequest($url);

                if (is_array($data)) {
                    self::$lastError = !empty($data['message'])
                        ? $data['message']
                        : Loc::getMessage('uplab.tilda_ERROR_API');

                    Helper::notifyError(self::$lastError . ' (' . $request . ')');
                    Logger::error(Loc::getMessage('uplab.tilda_LOG_API_ERROR'), [
                        'status'  => $data['status'] ?? '',
                        'message' => $data['message'] ?? '',
                        'url'     => Logger::maskUrl($url),
                    ]);
                } elseif ($content === false) {
                    // Об ошибке транспорта уже сообщил Request::makeRequest() —
                    // второе уведомление о том же сбое админу не нужно.
                    self::$lastError = Loc::getMessage('uplab.tilda_ERROR_NO_RESPONSE');

                    Logger::error(Loc::getMessage('uplab.tilda_LOG_NO_RESPONSE'), [
                        'url' => Logger::maskUrl($url),
                    ]);
                } else {
                    self::$lastError = Loc::getMessage('uplab.tilda_ERROR_INVALID_RESPONSE');

                    // Ответ пришёл, но разобрать его не удалось. Без уведомления
                    // такой сбой виден только в файловом журнале, а он по
                    // умолчанию выключен.
                    Helper::notifyError(self::$lastError . ' (' . $request . ')');
                    Logger::error(Loc::getMessage('uplab.tilda_LOG_INVALID_RESPONSE'), [
                        'json_error' => json_last_error_msg(),
                        'body'       => $content,
                        'url'        => Logger::maskUrl($url),
                    ]);
                }

                // Ответ с ошибкой не кэшируем и не заносим в реестр страниц:
                // иначе в списке появляются записи без названия и без кэша.
                $cache->abortDataCache();

                return [];
            }

            if (($data['status'] ?? '') !== 'FOUND' || empty($data['result'])) {
                // Формально не ошибка, но контента не будет — без этой записи
                // в журнале причина пустой страницы остаётся неочевидной.
                Logger::warning(Loc::getMessage('uplab.tilda_LOG_NO_RESULT'), [
                    'status'  => $data['status'] ?? '',
                    'message' => $data['message'] ?? '',
                    'body'    => $content,
                    'url'     => Logger::maskUrl($url),
                ]);

                // Пустой результат кэшируется только для списков: проект без
                // страниц — нормальный ответ. Для контента страницы ($noteInBase)
                // и для любого статуса, кроме FOUND, это сбой, и держать его
                // неделю в кэше нельзя — иначе статья пропадёт с сайта до
                // ручной очистки кэша.
                if ($noteInBase || ($data['status'] ?? '') !== 'FOUND') {
                    self::$lastError = Loc::getMessage('uplab.tilda_ERROR_EMPTY_RESULT');

                    Helper::notifyError(self::$lastError . ' (' . Request::describeRequest($url) . ')');

                    $cache->abortDataCache();

                    return [];
                }
            }

            Logger::info('API response cached', [
                'cacheId' => $cacheId,
                'status'  => $data['status'] ?? '',
                'result'  => self::describeResult($data['result'] ?? null),
            ]);

            $cache->endDataCache(['arrResponse' => $data]);

            if ($noteInBase) {
                CacheTable::addOrUpdate([
                    'TAG'        => $cacheId,
                    'NAME'       => $data['result']['title'] ?? '',
                    'PAGE_ID'    => isset($data['result']['id']) ? (int)$data['result']['id'] : null,
                    'PROJECT_ID' => isset($data['result']['projectid']) ? (int)$data['result']['projectid'] : null,
                    'DATE'       => new DateTime(),
                ]);
            }
        } elseif ($cache->initCache($cacheTime, $cacheId, $cacheDir, self::$cacheBaseDir)) {
            // Кэш появился между проверкой и попыткой записи: параллельный хит
            // выполнил запрос первым. Читаем то, что он сохранил, иначе метод
            // вернул бы пустоту при живых данных.
            $data = self::readCachedResponse($cache);

            Logger::debug('Cache hit after concurrent write', ['cacheId' => $cacheId]);
        } else {
            // Запись занята другим процессом, а готовых данных нет.
            self::$lastError = Loc::getMessage('uplab.tilda_ERROR_NO_RESPONSE');

            Logger::warning(Loc::getMessage('uplab.tilda_LOG_CACHE_BUSY'), [
                'cacheId'  => $cacheId,
                'cacheDir' => $cacheDir,
            ]);
        }

        return (is_array($data) && ($data['status'] ?? '') === 'FOUND' && !empty($data['result']))
            ? $data['result']
            : [];
    }

    /**
     * Достаёт сохранённый ответ API из открытого кэша.
     *
     * @param BitrixCache $cache Кэш, для которого уже отработал initCache().
     * @return array
     */
    private static function readCachedResponse($cache)
    {
        $result = $cache->getVars();

        return (is_array($result) && isset($result['arrResponse'])) ? $result['arrResponse'] : [];
    }

    /**
     * Кратко описывает поле `result` ответа для журнала: для списков — сколько
     * элементов, для страницы — её id, заголовок и размер HTML.
     *
     * @param mixed $result
     * @return string
     */
    private static function describeResult($result)
    {
        if (!is_array($result)) {
            return 'empty';
        }

        if (isset($result['id']) || isset($result['html'])) {
            return 'page id=' . ($result['id'] ?? '?')
                . ', title=' . ($result['title'] ?? '?')
                . ', html=' . strlen((string)($result['html'] ?? '')) . ' bytes';
        }

        return count($result) . ' item(s)';
    }

    /**
     * Полностью очищает кэш Tilda и реестр страниц в таблице {@see CacheTable}.
     *
     * @return void
     */
    public static function clearAllCache()
    {
        $cache = BitrixCache::createInstance();
        $cache->cleanDir('', self::$cacheBaseDir);

        Application::getConnection()->truncateTable(CacheTable::getTableName());
    }

    /**
     * Очищает кэш списков проектов и страниц (`getpageslist`,
     * `getprojectslist`), не затрагивая кэш контента страниц.
     *
     * @return void
     */
    public static function clearListCache()
    {
        $cache = BitrixCache::createInstance();
        $cache->cleanDir('getpageslist', self::$cacheBaseDir);
        $cache->cleanDir('getprojectslist', self::$cacheBaseDir);
    }

    /**
     * Очищает кэш контента одной страницы и удаляет её запись из реестра
     * {@see CacheTable}.
     *
     * @param string $tag Тег страницы (идентификатор кэша / первичный ключ в таблице).
     * @return void
     */
    public static function clearPageCache($tag)
    {
        $cache = BitrixCache::createInstance();
        $cache->cleanDir($tag, self::$cacheBaseDir);

        CacheTable::delete($tag);
    }
}
