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
use Uplab\Tilda\Service\Cache;

Loc::loadMessages(__FILE__);

/**
 * Фасад для работы с данными Tilda Publishing: получение списков проектов и
 * страниц, а также извлечение готового HTML-контента страницы в нужном режиме.
 *
 * Запросы к API проксируются через {@see Request}, результаты кэшируются
 * ({@see Cache}), текст приводится к кодировке сайта через {@see Helper}.
 *
 * @package Uplab\Tilda
 */
class Common
{
    /** Базовый URL Tilda API по умолчанию (используется, если настройка не задана). */
    const DEFAULT_API_URL = 'https://api.tildacdn.info/v1/';

    /** @var string Публичный ключ Tilda API (из настроек модуля). */
    public static $publickey;

    /** @var string Секретный ключ Tilda API (из настроек модуля). */
    public static $secretkey;

    /** @var string Базовый URL Tilda API (из настроек модуля, иначе {@see Common::DEFAULT_API_URL}). */
    public static $apiUrl;

    /** @var string Идентификатор модуля. */
    public static $module_id = 'uplab.tilda';

    /** @var bool Флаг однократной загрузки ключей API (см. {@see Common::getOptions()}). */
    public static $inc = false;

    /**
     * Однократно загружает настройки доступа к Tilda API в статические свойства
     * {@see Common::$publickey}, {@see Common::$secretkey} и {@see Common::$apiUrl}.
     *
     * Повторные вызовы игнорируются за счёт флага {@see Common::$inc}.
     *
     * @return void
     */
    static function getOptions()
    {
        if (self::$inc) {
            return;
        }
        self::$inc = true;
        self::$publickey = Option::get(self::$module_id, "UPT_PUBLIC_KEY");
        self::$secretkey = Option::get(self::$module_id, "UPT_SECRET_KEY");

        // Проверка адреса API (HTTPS + домен Tilda) живёт в одном месте — в Request.
        self::$apiUrl = Request::resolveApiUrl();
    }

    /**
     * Возвращает сырые данные списка проектов из Tilda API без какой-либо
     * обработки текстовых полей.
     *
     * В отличие от {@see Common::getProjects()}, заголовки и описания проектов
     * не очищаются от кавычек и не конвертируются по кодировке — данные
     * возвращаются ровно такими, какими их отдаёт Tilda API (поле `result`
     * ответа метода `getprojectslist`).
     *
     * @return array Список проектов или пустой массив при ошибке.
     */
    public static function getRawProjectsData()
    {
        self::getOptions();
        Logger::debug('getRawProjectsData called');
        return Request::getData('getprojectslist');
    }

    /**
     * Возвращает сырые данные списка страниц проекта из Tilda API без
     * какой-либо обработки текстовых полей.
     *
     * В отличие от {@see Common::getPages()}, заголовки и описания страниц
     * не очищаются от кавычек и не конвертируются по кодировке — данные
     * возвращаются ровно такими, какими их отдаёт Tilda API (поле `result`
     * ответа метода `getpageslist`).
     *
     * @param int|string $projectId Идентификатор проекта Tilda.
     * @return array Список страниц или пустой массив при ошибке или пустом $projectId.
     */
    public static function getRawPagesData($projectId)
    {
        if (empty($projectId)) {
            return [];
        }
        self::getOptions();
        Logger::debug('getRawPagesData called', ['projectId' => $projectId]);
        return Request::getData('getpageslist', ['projectid' => $projectId]);
    }

    /**
     * Возвращает сырые данные страницы из Tilda API без какой-либо обработки.
     *
     * В отличие от {@see Common::getPageContent()} и смежных методов, HTML
     * страницы не разбирается, ассеты не вычленяются, кодировка не
     * конвертируется — возвращается поле `result` ответа метода `getpagefull`
     * напрямую. Типичные ключи результата:
     * - `id`, `title`, `descr`, `alias`, `date` — метаданные страницы;
     * - `html` — полный HTML документа;
     * - `js`, `css`, `images` — массивы подключённых ресурсов;
     * - `projectid` — идентификатор проекта.
     *
     * Ответ кэшируется так же, как и при вызове через тег `[UPLABTILDA ...]`.
     *
     * @param int|string $pageId Идентификатор страницы Tilda.
     * @return array Данные страницы или пустой массив при ошибке или пустом $pageId.
     */
    public static function getRawPageData($pageId)
    {
        if (empty($pageId)) {
            return [];
        }
        self::getOptions();
        Logger::debug('getRawPageData called', ['pageId' => $pageId]);
        return Request::getData('getpagefull', ['pageid' => $pageId]);
    }

    /**
     * Возвращает список проектов Tilda (метод API `getprojectslist`).
     *
     * Результат кэшируется в статической переменной на время запроса.
     * Поля `title` и `descr` приводятся к нужной кодировке, из `title`
     * вырезаются кавычки.
     *
     * @param bool $unicode Если false — текстовые поля конвертируются в windows-1251.
     * @return array|false Список проектов либо false при ошибке запроса.
     */
    public static function getProjects($unicode = true)
    {
        static $projectsList = false;

        if ($projectsList !== false) {
            return $projectsList;
        }

        self::getOptions();

        $projectsList = Request::getData('getprojectslist');

        if ($projectsList) {
            foreach ($projectsList as &$value) {
                $value['title'] = Helper::convert2Win1251($value['title'], !$unicode);
                $value['descr'] = Helper::convert2Win1251($value['descr'], !$unicode);
                $value['title'] = str_replace(['"', "'"], '', $value['title']);
            }
        }

        return $projectsList;
    }

    /**
     * Возвращает список страниц проекта Tilda (метод API `getpageslist`).
     *
     * Поля `title` и `descr` приводятся к нужной кодировке, из `title`
     * вырезаются кавычки.
     *
     * @param int|string|false $projectId Идентификатор проекта Tilda.
     * @param bool             $unicode   Если false — текстовые поля конвертируются в windows-1251.
     * @return array Список страниц (пустой массив, если проект не задан).
     */
    public static function getPages($projectId = false, $unicode = true)
    {
        if (empty($projectId)) {
            return array();
        }

        $pagesList = Request::getData('getpageslist', ['projectid' => $projectId]);

        if (!empty($pagesList)) {
            foreach ($pagesList as &$value) {
                $value['title'] = Helper::convert2Win1251($value['title'], !$unicode);
                $value['descr'] = Helper::convert2Win1251($value['descr'], !$unicode);
                $value['title'] = str_replace(['"', "'"], '', $value['title']);
            }
        }

        return $pagesList;
    }

    /**
     * Возвращает список страниц проекта в виде ассоциативного массива
     * `id => title`, пригодного для выпадающих списков в админке.
     *
     * @param int|string|false $projectId Идентификатор проекта Tilda.
     * @param bool             $unicode   Если false — заголовки конвертируются в windows-1251.
     * @return array Массив вида [id страницы => заголовок].
     */
    public static function getAssocPagesList($projectId = false, $unicode = true)
    {
        if (empty($projectId)) {
            return [
                0 => Loc::getMessage('uplab.tilda_CHECK_PAGE'),
            ];
        }

        $result = self::getAssocPagesResult($projectId, $unicode);

        return $result['pages'];
    }

    /**
     * Возвращает список страниц проекта вместе с признаком неудачного запроса.
     *
     * Отличается от {@see Common::getAssocPagesList()} только тем, что
     * позволяет отличить «в проекте нет страниц» от «список не загрузился»:
     * оба случая дают пустой массив, и без этого признака интерфейс сообщает
     * об отсутствии страниц там, где Tilda не ответила.
     *
     * @param int|string|false $projectId Идентификатор проекта Tilda.
     * @param bool             $unicode   Если false — заголовки конвертируются в windows-1251.
     * @return array{pages: array, error: string|null} Список вида [id => заголовок] и текст ошибки (null, если её не было).
     */
    public static function getAssocPagesResult($projectId = false, $unicode = true)
    {
        if (empty($projectId)) {
            return ['pages' => [], 'error' => null];
        }

        $pagesList = self::getPages($projectId, $unicode);
        $error     = Cache::getLastError();

        $arPages = array();

        if (!empty($pagesList)) {
            foreach ($pagesList as $page) {
                $arPages[$page['id']] = $page['title'];
            }
        }

        return ['pages' => $arPages, 'error' => $error];
    }

    /**
     * Возвращает список проектов в виде ассоциативного массива `id => title`,
     * пригодного для выпадающих списков в админке.
     *
     * @return array Массив вида [id проекта => заголовок].
     */
    public static function getAssocProjectsList()
    {
        $arProjects = array();
        $projectsList = self::getProjects();

        if (!empty($projectsList)) {
            foreach ($projectsList as $project) {
                $arProjects[$project['id']] = $project['title'];
            }
        }

        return $arProjects;
    }

    /**
     * Получает HTML страницы Tilda для режима «по умолчанию» (контент
     * встраивается в шаблон сайта).
     *
     * Из ответа API (`getpagefull`) извлекаются ассеты `<head>` (стили и
     * скрипты) и содержимое `<body>`. Если `<body>` не распарсился —
     * применяется fallback-извлечение контейнера `<div id="allrecords">`.
     * Результат оборачивается в маркеры `<!--<TILDA>-->...<!--</TILDA>-->`,
     * после чего вызывается событие `onBeforeContentReplace`.
     *
     * @param int   $page   Идентификатор страницы Tilda.
     * @param array $params Параметры тега `[UPLABTILDA ...]` (зарезервировано).
     * @return string Готовый HTML-фрагмент либо пустая строка.
     */
    static function getPageContent($page, $params = array())
    {
        Logger::debug('getPageContent called', ['pageId' => $page]);
        $data = Request::getData('getpagefull', ['pageid' => $page]);

        $content = '';

        // Assets
        if (!empty($data['html'])) {
            $headMatches = [];
            preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $data['html'], $headMatches);

            if (!empty($headMatches)) {
                $headContent = $headMatches[1];

                $assets = [];

                // Styles
                $styleMatches = [];
                preg_match_all('/<link\b[^>]*\brel=["\']stylesheet["\'][^>]*>/is', $headContent, $styleMatches, PREG_SET_ORDER);

                foreach ($styleMatches as $match) {
                    $assets[] = $match[0];
                }

                // Scripts
                $scriptMatches = [];
                preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $headContent, $scriptMatches, PREG_SET_ORDER);

                foreach ($scriptMatches as $match) {
                    $assets[] = $match[0];
                }

                if (!empty($assets)) {
                    $content = implode('', $assets);
                }
            }
        }

        // HTML
        if (!empty($data['html'])) {
            $matches = array();
            preg_match_all('/<body\b[^>]*>(.*?)<\/body>/is', $data['html'], $matches, PREG_SET_ORDER);
            $html = Helper::convert2Win1251($matches[0][1] ?? '');

            // Fallback: extract #allrecords container if <body> regex failed
            if (empty($html)) {
                Logger::warning(Loc::getMessage('uplab.tilda_LOG_BODY_FALLBACK'), ['pageId' => $page]);

                $pos = stripos($data['html'], '<div id="allrecords"');
                if ($pos !== false) {
                    $endBodyPos = strripos($data['html'], '</body>');
                    $rawHtml = $endBodyPos !== false
                        ? substr($data['html'], $pos, $endBodyPos - $pos)
                        : substr($data['html'], $pos);
                    $html = Helper::convert2Win1251($rawHtml);
                }
            }

            if (!empty($html)) {
                $content .= $html;
            }
        }

        Logger::debug('getPageContent result', [
            'pageId' => $page,
            'source' => strlen((string)($data['html'] ?? '')) . ' bytes',
            'output' => strlen($content) . ' bytes',
        ]);

        // Include page content
        if (!empty($content)) {
            $module_version = "";
            if ($info = \CModule::CreateModuleObject(self::$module_id)) {
                $module_version = $info->MODULE_VERSION;
            }

            $content = "<!--<TILDA ver=\"{$module_version}\">-->" . $content . "<!--</TILDA>-->";

            Helper::checkEventBeforeContentReplace($content);
        }

        return $content;
    }

    /**
     * Получает полный HTML страницы Tilda для режима «без шаблона сайта»
     * (`HIDEPAGETEMPLATE=Y`).
     *
     * Возвращается весь документ из ответа API без выделения `<head>`/`<body>`,
     * приведённый к кодировке сайта; затем вызывается событие
     * `onBeforeContentReplace`.
     *
     * @param int $page Идентификатор страницы Tilda.
     * @return string Полный HTML страницы либо пустая строка.
     */
    static function getPageFullContent($page)
    {
        Logger::debug('getPageFullContent called', ['pageId' => $page]);
        $data = Request::getData('getpagefull', ['pageid' => $page]);

        $content = '';

        if (!empty($data['html'])) {
            $content = Helper::convert2Win1251($data['html']);

            Helper::checkEventBeforeContentReplace($content);
        }

        Logger::debug('getPageFullContent result', [
            'pageId' => $page,
            'output' => strlen($content) . ' bytes',
        ]);

        return $content;
    }

    /**
     * Получает HTML страницы Tilda, разделённый на ассеты и тело, для режима
     * с выносом ресурсов (`MOVERESOURCESTO=HEADEND|BODYEND`).
     *
     * Из ответа API извлекаются ассеты `<head>` (стили `<link>`/`<style>` и
     * скрипты `<script>`) и содержимое `<body>` (с fallback по
     * `<div id="allrecords">`); из тела вырезаются `<style>` и `<script>`,
     * чтобы они не дублировались. Обе части оборачиваются в маркеры
     * `<!--<TILDA>-->...<!--</TILDA>-->`; для тела вызывается событие
     * `onBeforeContentReplace`.
     *
     * @param int $page Идентификатор страницы Tilda.
     * @return array{assets: string, html: string} Ассеты для вставки в head/body и HTML тела.
     */
    static function getPageParts($page)
    {
        Logger::debug('getPageParts called', ['pageId' => $page]);
        $data = Request::getData('getpagefull', ['pageid' => $page]);

        $module_version = "";
        if ($info = \CModule::CreateModuleObject(self::$module_id)) {
            $module_version = $info->MODULE_VERSION;
        }

        // Getting assets from <head>
        $assetsString = '';

        if (!empty($data['html'])) {
            $assets = [];

            // Styles (link tag)
            $styleMatches = [];
            preg_match_all('/<link\b[^>]*\brel=["\']stylesheet["\'][^>]*>/is', $data['html'], $styleMatches, PREG_SET_ORDER);

            foreach ($styleMatches as $match) {
                $assets[] = $match[0];
            }

            // Styles (style tag)
            $styleTagMatches = [];
            preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $data['html'], $styleTagMatches, PREG_SET_ORDER);

            foreach ($styleTagMatches as $match) {
                $assets[] = $match[0];
            }

            // Scripts
            $scriptMatches = [];
            preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $data['html'], $scriptMatches, PREG_SET_ORDER);

            foreach ($scriptMatches as $match) {
                $assets[] = $match[0];
            }

            if (!empty($assets)) {
                $assetsString = implode(PHP_EOL, $assets);

                $assetsString = "<!--<TILDA ver=\"{$module_version}\">-->{$assetsString}<!--</TILDA>-->";
            }
        }

        // Getting HTML from body
        $html = '';

        if (!empty($data['html'])) {
            $matches = array();
            preg_match_all('/<body\b[^>]*>(.*?)<\/body>/is', $data['html'], $matches, PREG_SET_ORDER);

            $html = Helper::convert2Win1251($matches[0][1] ?? '');

            // Fallback: extract #allrecords container if <body> regex failed
            if (empty($html)) {
                Logger::warning(Loc::getMessage('uplab.tilda_LOG_BODY_FALLBACK'), ['pageId' => $page]);

                $pos = stripos($data['html'], '<div id="allrecords"');
                if ($pos !== false) {
                    $endBodyPos = strripos($data['html'], '</body>');
                    $rawHtml = $endBodyPos !== false
                        ? substr($data['html'], $pos, $endBodyPos - $pos)
                        : substr($data['html'], $pos);
                    $html = Helper::convert2Win1251($rawHtml);
                }
            }

            // Removing assets from HTML
            // Styles
            $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

            // Scripts
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

            $html = "<!--<TILDA ver=\"{$module_version}\">-->{$html}<!--</TILDA>-->";

            Helper::checkEventBeforeContentReplace($html);
        }

        Logger::debug('getPageParts result', [
            'pageId' => $page,
            'source' => strlen((string)($data['html'] ?? '')) . ' bytes',
            'assets' => strlen($assetsString) . ' bytes',
            'html'   => strlen($html) . ' bytes',
        ]);

        return [
            'assets' => $assetsString,
            'html'    => $html,
        ];
    }
}
