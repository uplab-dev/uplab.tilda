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
use Bitrix\Main\Config\Option;
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

    /**
     * Срок жизни резервной копии ответа, секунд (30 дней).
     *
     * Копия живёт заметно дольше основного кэша: она нужна ровно в тот момент,
     * когда основной уже истёк, а Tilda не отвечает.
     */
    const STALE_TTL = 2592000;

    /** @var string Базовый каталог кэша Битрикс для данных Tilda. */
    protected static $cacheBaseDir = 'cache_tilda';

    /**
     * @var string Базовый каталог резервных копий ответов.
     *
     * Отдельный каталог, а не тот же самый с другим сроком: основной кэш
     * перезаписывается и чистится по своим правилам, а копия должна пережить
     * истечение срока — только тогда её можно отдать вместо пустой страницы.
     */
    protected static $staleBaseDir = 'cache_tilda_stale';

    /**
     * @var array Страницы, отданные из резервной копии в текущем хите:
     *            `pageId => дата последней успешной загрузки`.
     *
     * Нужен {@see \Uplab\Tilda\Replace}, чтобы пометить такую страницу для
     * сотрудников: контент-менеджер должен видеть, что на проде старая версия,
     * а не считать, что его правки не сохранились.
     */
    private static $staleServed = [];

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

            if ($noteInBase) {
                // После обновления с версии без резервного кэша основной кэш
                // ещё может быть жив. Создаём копию из него заранее, иначе при
                // первой же неудаче после истечения основного кэша fallback
                // окажется пустым.
                self::backfillStaleCopy($cacheId, $cacheDir, $data);
            }

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

                // Резервная копия допустима только при подтверждённой ошибке
                // транспорта. Ответ API со статусом ERROR или некорректный
                // ответ может означать удаление страницы, отзыв доступа либо
                // другую авторитетную причину не публиковать старый контент.
                return ($content === false)
                    ? self::serveStale($url, $cacheId, $cacheDir, $noteInBase)
                    : [];
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

                    // Пустой/не-FOUND ответ — содержательный ответ API, а не
                    // транспортный сбой. Старую страницу в этом случае не
                    // публикуем: она могла быть снята с публикации в Tilda.
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
                self::writeStaleCopy($cacheId, $cacheDir, $data);

                // Страница снова загружается — снимаем плашку об устаревшей
                // копии, иначе она осталась бы висеть после того, как проблема
                // прошла, и следующий такой же сбой прошёл бы незамеченным.
                Helper::clearNotifyOnce(self::staleNotifyKey($cacheId));

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

            if ($noteInBase) {
                self::backfillStaleCopy($cacheId, $cacheDir, $data);
            }

            Logger::debug('Cache hit after concurrent write', ['cacheId' => $cacheId]);
        } else {
            // Запись занята другим процессом, а готовых данных нет.
            self::$lastError = Loc::getMessage('uplab.tilda_ERROR_NO_RESPONSE');

            Logger::warning(Loc::getMessage('uplab.tilda_LOG_CACHE_BUSY'), [
                'cacheId'  => $cacheId,
                'cacheDir' => $cacheDir,
            ]);

            // Показать нечего ровно так же, как при сбое запроса, — если копия
            // есть и режим включён, отдаём её, а не пустой блок.
            return self::serveStale($url, $cacheId, $cacheDir, $noteInBase);
        }

        if (is_array($data) && ($data['status'] ?? '') === 'FOUND' && !empty($data['result'])) {
            $pageId = self::extractPageId($url);
            if ($pageId > 0) {
                unset(self::$staleServed[$pageId]);
            }

            return $data['result'];
        }

        return [];
    }

    /**
     * Отдаёт содержимое страницы из резервной копии, если такой режим включён.
     *
     * Вызывается только при транспортной ошибке либо внутренней гонке кэша.
     * Содержательные ответы API сюда намеренно не попадают: старая страница не
     * должна оставаться публичной после удаления или снятия с публикации.
     * Без включённой опции `UPT_STALE_ON_ERROR` поведение прежнее — пустой
     * массив, и блок исчезает со страницы. Для списков проектов и страниц
     * ($noteInBase = false) резерв не ведётся: там пустой ответ ничего не ломает.
     *
     * @param string $url        URL запроса к Tilda API.
     * @param string $cacheId    Идентификатор кэша.
     * @param string $cacheDir   Подкаталог кэша.
     * @param bool   $noteInBase Признак контента страницы (а не списка).
     * @return array Содержимое страницы из копии либо пустой массив.
     */
    private static function serveStale($url, $cacheId, $cacheDir, $noteInBase)
    {
        if (!$noteInBase || !self::isStaleEnabled()) {
            return [];
        }

        $result = self::readStaleCopy($cacheId, $cacheDir);

        if ($result === null) {
            return [];
        }

        $row    = CacheTable::getByPrimary($cacheId)->fetch();
        $date   = !empty($row['DATE']) ? (string)$row['DATE'] : '';
        $name   = !empty($row['NAME']) ? (string)$row['NAME'] : Request::describeRequest($url);
        $pageId = self::extractPageId($url);

        if ($pageId > 0) {
            self::$staleServed[$pageId] = $date;
        }

        Logger::warning(Loc::getMessage('uplab.tilda_LOG_STALE_SERVED'), [
            'pageId' => $pageId,
            'date'   => $date,
            'url'    => Logger::maskUrl($url),
        ]);

        // Плашка в админке — одна на состояние: notifyOnce() запоминает текст,
        // а он меняется только вместе с датой копии. Иначе каждый хит
        // публичной страницы возвращал бы закрытое администратором сообщение.
        Helper::notifyOnce(
            self::staleNotifyKey($cacheId),
            Loc::getMessage('uplab.tilda_STALE_NOTIFY', [
                '#NAME#' => htmlspecialcharsbx($name),
                '#DATE#' => htmlspecialcharsbx($date),
            ])
        );

        return $result;
    }

    /**
     * Сохраняет резервную копию успешного ответа.
     *
     * @param string   $cacheId  Идентификатор кэша.
     * @param string   $cacheDir Подкаталог кэша.
     * @param array    $data     Ответ API целиком (со `status` и `result`).
     * @param int|null $fetchedAt Unix-время исходной успешной загрузки.
     * @param bool     $replace   Удалить ли предыдущую копию перед записью.
     * @return void
     */
    private static function writeStaleCopy(
        $cacheId,
        $cacheDir,
        array $data,
        $fetchedAt = null,
        $replace = true
    )
    {
        $fetchedAt = ($fetchedAt !== null) ? (int)$fetchedAt : time();

        $cache = BitrixCache::createInstance();

        // При свежей загрузке копию перезаписываем: startDataCache() над живой
        // копией вернул бы false, и в резерве осталась бы предыдущая версия.
        // Backfill передаёт $replace = false, поскольку уже проверил отсутствие.
        if ($replace) {
            $cache->clean($cacheId, $cacheDir, self::$staleBaseDir);
        }

        if ($cache->startDataCache(self::STALE_TTL, $cacheId, $cacheDir, [], self::$staleBaseDir)) {
            $cache->endDataCache([
                'arrResponse' => $data,
                // Bitrix file cache checks TTL from the cache file creation
                // time supplied to initCache(), not the stored dateexpire.
                // Keep the source timestamp explicitly so a backfilled copy
                // cannot live 30 additional days from the migration hit.
                'fetchedAt'   => $fetchedAt,
            ]);
        }

        self::writeStaleMetadata($cacheId, $cacheDir, $fetchedAt, $replace);
    }

    /**
     * Сохраняет маленький маркер наличия резервной копии.
     *
     * Без отдельного маркера проверка на каждом попадании в основной кэш
     * десериализовала бы второй экземпляр полного HTML страницы. Маркер лежит
     * в том же каталоге, поэтому штатные операции очистки удаляют оба файла.
     *
     * @param string $cacheId  Идентификатор кэша страницы.
     * @param string $cacheDir Подкаталог кэша.
     * @param int    $fetchedAt Unix-время исходной загрузки.
     * @param bool   $replace  Удалить ли предыдущий маркер.
     * @return void
     */
    private static function writeStaleMetadata($cacheId, $cacheDir, $fetchedAt, $replace = true)
    {
        $cache  = BitrixCache::createInstance();
        $metaId = self::staleMetaId($cacheId);

        if ($replace) {
            $cache->clean($metaId, $cacheDir, self::$staleBaseDir);
        }

        if ($cache->startDataCache(self::STALE_TTL, $metaId, $cacheDir, [], self::$staleBaseDir)) {
            $cache->endDataCache(['fetchedAt' => (int)$fetchedAt]);
        }
    }

    /**
     * Читает маркер резервной копии без загрузки полного HTML.
     *
     * @param string $cacheId  Идентификатор кэша страницы.
     * @param string $cacheDir Подкаталог кэша.
     * @return int|null Unix-время загрузки либо null, если маркера нет/он истёк.
     */
    private static function readStaleMetadata($cacheId, $cacheDir)
    {
        $cache  = BitrixCache::createInstance();
        $metaId = self::staleMetaId($cacheId);

        if (!$cache->initCache(self::STALE_TTL, $metaId, $cacheDir, self::$staleBaseDir)) {
            return null;
        }

        $vars      = $cache->getVars();
        $fetchedAt = (is_array($vars) && isset($vars['fetchedAt']))
            ? (int)$vars['fetchedAt']
            : 0;

        if ($fetchedAt <= 0 || $fetchedAt < time() - self::STALE_TTL) {
            $cache->clean($metaId, $cacheDir, self::$staleBaseDir);
            return null;
        }

        return $fetchedAt;
    }

    /**
     * Создаёт отсутствующую резервную копию из ещё живого основного кэша.
     *
     * Это миграционный путь для страниц, закэшированных до версии 3.3.3.
     * Оставшийся TTL считается от даты исходной загрузки, поэтому backfill не
     * продлевает срок хранения страницы сверх {@see self::STALE_TTL}.
     *
     * @param string $cacheId  Идентификатор кэша.
     * @param string $cacheDir Подкаталог кэша.
     * @param array  $data     Ответ из основного кэша.
     * @return void
     */
    private static function backfillStaleCopy($cacheId, $cacheDir, array $data)
    {
        if (
            ($data['status'] ?? '') !== 'FOUND'
            || empty($data['result'])
            || self::hasStaleCopy($cacheId, $cacheDir)
        ) {
            return;
        }

        $row = CacheTable::getByPrimary($cacheId)->fetch();
        if (empty($row['DATE']) || !is_object($row['DATE']) || !method_exists($row['DATE'], 'getTimestamp')) {
            return;
        }

        // Копия отсутствует, поэтому не чистим каталог перед записью: если два
        // процесса одновременно выполняют backfill, второй увидит результат
        // первого внутри startDataCache(), не удаляя уже готовый файл.
        self::writeStaleCopy($cacheId, $cacheDir, $data, $row['DATE']->getTimestamp(), false);
    }

    /**
     * Читает резервную копию ответа.
     *
     * @param string $cacheId  Идентификатор кэша.
     * @param string $cacheDir Подкаталог кэша.
     * @return array|null Поле `result` сохранённого ответа либо null, если
     *                    копии нет, она старше {@see self::STALE_TTL} или в ней
     *                    нет содержимого.
     */
    private static function readStaleCopy($cacheId, $cacheDir, $ensureMetadata = false)
    {
        $cache = BitrixCache::createInstance();

        if (!$cache->initCache(self::STALE_TTL, $cacheId, $cacheDir, self::$staleBaseDir)) {
            return null;
        }

        $vars = $cache->getVars();
        $data = (is_array($vars) && isset($vars['arrResponse']))
            ? $vars['arrResponse']
            : [];

        $fetchedAt = (is_array($vars) && isset($vars['fetchedAt']))
            ? (int)$vars['fetchedAt']
            : 0;

        // Поддерживаем копии, созданные первоначальной реализацией без
        // fetchedAt: дата реестра — тот же момент успешной загрузки.
        if ($fetchedAt <= 0) {
            $row = CacheTable::getByPrimary($cacheId)->fetch();
            if (!empty($row['DATE']) && is_object($row['DATE']) && method_exists($row['DATE'], 'getTimestamp')) {
                $fetchedAt = $row['DATE']->getTimestamp();
            }
        }

        if ($fetchedAt <= 0 || $fetchedAt < time() - self::STALE_TTL) {
            $cache->clean($cacheId, $cacheDir, self::$staleBaseDir);
            $cache->clean(self::staleMetaId($cacheId), $cacheDir, self::$staleBaseDir);
            return null;
        }

        if (!is_array($data) || ($data['status'] ?? '') !== 'FOUND' || empty($data['result'])) {
            $cache->clean(self::staleMetaId($cacheId), $cacheDir, self::$staleBaseDir);
            return null;
        }

        if ($ensureMetadata) {
            self::writeStaleMetadata($cacheId, $cacheDir, $fetchedAt, false);
        }

        return $data['result'];
    }

    /**
     * Проверяет фактическое наличие пригодной резервной копии страницы.
     *
     * @param string      $cacheId  Идентификатор кэша.
     * @param string|null $cacheDir Подкаталог; по умолчанию каталог страницы.
     * @return bool
     */
    public static function hasStaleCopy($cacheId, $cacheDir = null)
    {
        $cacheDir = ($cacheDir !== null) ? $cacheDir : '/' . $cacheId . '/';

        if (self::readStaleMetadata($cacheId, $cacheDir) !== null) {
            return true;
        }

        // Копии, созданные первоначальной реализацией 3.3.3, не содержат
        // метаданных. Один раз читаем такую копию целиком и создаём маркер;
        // следующие проверки будут дешёвыми.
        return self::readStaleCopy($cacheId, $cacheDir, true) !== null;
    }

    /**
     * Включён ли режим отдачи устаревшей копии при сбое Tilda.
     *
     * @return bool
     */
    public static function isStaleEnabled()
    {
        return Option::get('uplab.tilda', 'UPT_STALE_ON_ERROR', 'N') === 'Y';
    }

    /**
     * Возвращает дату последней успешной загрузки страницы, если в текущем хите
     * она была отдана из резервной копии.
     *
     * @param int|string $pageId Идентификатор страницы Tilda.
     * @return string|null null, если страница отдана из актуального кэша или
     *                     загружена заново.
     */
    public static function getStaleServed($pageId)
    {
        return self::$staleServed[(int)$pageId] ?? null;
    }

    /**
     * Ключ разового уведомления об устаревшей копии страницы.
     *
     * @param string $cacheId Идентификатор кэша (тег страницы).
     * @return string
     */
    private static function staleNotifyKey($cacheId)
    {
        // Имя опции у notifyOnce() — 'UPT_NOTIFIED_' + ключ, а поле
        // b_option.NAME ограничено 50 символами: полный 32-символьный тег
        // в него не помещается.
        return 'STALE_' . substr((string)$cacheId, 0, 12);
    }

    /**
     * Идентификатор маленького маркера резервной копии.
     *
     * @param string $cacheId Идентификатор кэша страницы.
     * @return string
     */
    private static function staleMetaId($cacheId)
    {
        return (string)$cacheId . '_meta';
    }

    /**
     * Достаёт идентификатор страницы Tilda из URL запроса.
     *
     * @param string $url Полный URL запроса к Tilda API.
     * @return int 0, если параметра `pageid` в запросе нет.
     */
    private static function extractPageId($url)
    {
        $parts = parse_url((string)$url);

        if (empty($parts['query'])) {
            return 0;
        }

        $query = [];
        parse_str($parts['query'], $query);

        return isset($query['pageid']) ? (int)$query['pageid'] : 0;
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
        $cache->cleanDir('', self::$staleBaseDir);

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
        // Явная очистка кэша страницы означает «забыть эту страницу», поэтому
        // резервную копию тоже удаляем: иначе после сброса при первом же сбое
        // Tilda вернулось бы то самое содержимое, от которого избавлялись.
        // Принудительное обновление ссылкой `?clear_cache=Y` файлов не удаляет,
        // и там копия остаётся доступной.
        $cache->cleanDir($tag, self::$staleBaseDir);

        Helper::clearNotifyOnce(self::staleNotifyKey($tag));

        CacheTable::delete($tag);
    }
}
