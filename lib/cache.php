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

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache as BitrixCache;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;

Loc::loadMessages(__FILE__);

/**
 * Кэширование ответов Tilda API поверх {@see \Bitrix\Main\Data\Cache}.
 *
 * Хранит данные в базовом каталоге кэша `cache_tilda` со сроком жизни 7 дней
 * и ведёт реестр закэшированных страниц в таблице {@see CacheTable}.
 * Предоставляет методы точечной и полной очистки кэша.
 *
 * @package Uplab\Tilda
 */
class Cache
{
    /** @var string Базовый каталог кэша Битрикс для данных Tilda. */
    protected static $cacheBaseDir = 'cache_tilda';

    /**
     * Возвращает данные из кэша либо, при промахе, запрашивает их по URL и
     * кэширует на 7 дней.
     *
     * При ответе со статусом `ERROR` кэширование прерывается, а сообщение
     * логируется через {@see Helper::notifyError()}. Если $noteInBase = true,
     * запись о странице добавляется/обновляется в {@see CacheTable}.
     *
     * @param string $url        Полный URL запроса к Tilda API.
     * @param string $cacheId    Идентификатор кэша (обычно md5 от URL).
     * @param string $cacheDir   Подкаталог кэша.
     * @param bool   $noteInBase Регистрировать ли запись в таблице реестра страниц.
     * @return array Поле `result` ответа Tilda либо пустой массив.
     */
    static function cache($url, $cacheId, $cacheDir, $noteInBase = false)
    {
        $cacheTime = 604800;

        $data = [];

        $cache = BitrixCache::createInstance();

        if ($cache->initCache($cacheTime, $cacheId, $cacheDir, self::$cacheBaseDir)) {
            $result = $cache->getVars();
            $data = $result['arrResponse'];
        } elseif ($cache->startDataCache($cacheTime, $cacheId, $cacheDir, [], self::$cacheBaseDir)) {
            $content = Request::makeRequest($url);

            $data = json_decode($content, true);

            if (!is_array($data) || ($data['status'] ?? '') === 'ERROR') {
                if (!empty($data['message'])) {
                    Helper::notifyError($data['message']);
                }

                $cache->abortDataCache();
            }

            $cache->endDataCache(
                [
                    'arrResponse' => $data
                ]
            );

            if ($noteInBase) {
                CacheTable::addOrUpdate([
                    'TAG'        => $cacheId,
                    'NAME'       => $data['result']['title'] ?? '',
                    'PAGE_ID'    => isset($data['result']['id']) ? (int)$data['result']['id'] : null,
                    'PROJECT_ID' => isset($data['result']['projectid']) ? (int)$data['result']['projectid'] : null,
                    'DATE'       => new DateTime(),
                ]);
            }
        }

        return (is_array($data) && ($data['status'] ?? '') === 'FOUND' && !empty($data['result'])) ?
            $data['result'] :
            [];
    }

    /**
     * Полностью очищает кэш Tilda и реестр страниц в таблице {@see CacheTable}.
     *
     * @return void
     */
    static function clearAllCache()
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
    static function clearListCache()
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
    static public function clearPageCache($tag)
    {
        $cache = BitrixCache::createInstance();
        $cache->cleanDir($tag, self::$cacheBaseDir);

        CacheTable::delete($tag);
    }
}
