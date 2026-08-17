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
use Uplab\Tilda\Diag\Logger;
use Uplab\Tilda\Enum\MoveResourcesTarget;

/**
 * Замена тегов `[UPLABTILDA ...]` на HTML-контент страниц Tilda в буфере вывода.
 *
 * Вызывается по событию `main:OnEndBufferContent`, разбирает параметры тега
 * (`PROJECT`, `PAGE`, `HIDEPAGETEMPLATE`, `MOVERESOURCESTO`) и выбирает один из
 * режимов вставки контента, получая HTML через {@see Common}. Также чистит
 * теги из индексируемого поиском контента.
 *
 * @package Uplab\Tilda
 */
class Replace
{
    /**
     * Находит в буфере вывода все теги `[UPLABTILDA ...]` и заменяет их на
     * контент соответствующих страниц Tilda.
     *
     * В публичной части сайта (не в админке) для каждого вхождения вызывается
     * {@see Replace::replaceContent()}; дополнительно совместимостный фикс
     * jQuery `.load` → `.on('load', ...)` и удаление кода в маркерах
     * `<!--UTilda-->...<!--EndUTilda-->`.
     *
     * @param string $content Буфер вывода страницы (передаётся по ссылке и изменяется).
     * @return bool false в админ-разделе, иначе true.
     */
    static function tagReplace(&$content)
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if ($request->isAdminSection()) {
            return false;
        }

        // Replace all occurrences of tag [UPLABTILDA ... ]
        if (preg_match_all("/\[(UPLABTILDA [^\]]+)\]/", $content, $matches)) {
            Logger::debug('Tilda tags found on page', [
                'count' => count($matches[1]),
                'uri'   => $request->getRequestUri(),
            ]);

            if (is_array($matches[1])) {
                foreach ($matches[1] as $k => $match) {
                    $content = self::replaceContent($content, $match, $matches[0][$k]);
                }
            } else {
                $content = self::replaceContent($content, $matches[1], $matches[0]);
            }

            // Replace JQuery .load method with .on
            $content = str_replace("$(window).load(function()", "$(window).on('load', function ()", $content);

            // Remove content marked with tag <!--UTilda--><!--EndUTilda-->
            Helper::removeCommentedCode($content);
        }

        return true;
    }

    /**
     * Заменяет одно вхождение тега Tilda на HTML страницы в зависимости от
     * режима вставки.
     *
     * Разбирает параметры тега и выбирает режим:
     * — `HIDEPAGETEMPLATE=Y` — полный HTML без шаблона сайта
     *   ({@see Common::getPageFullContent()});
     * — `MOVERESOURCESTO=HEADEND|BODYEND` — вынос ассетов в конец head/body и
     *   замена тега на тело страницы ({@see Common::getPageParts()});
     * — по умолчанию — встраивание контента в шаблон сайта
     *   ({@see Common::getPageContent()}).
     *
     * @param string $content  Буфер вывода страницы.
     * @param string $match    Содержимое тега без скобок (например, `UPLABTILDA PROJECT=.. PAGE=..`).
     * @param string $tildaTag Полный текст тега вместе со скобками `[...]` для замены.
     * @return string Контент с выполненной заменой.
     */
    static function replaceContent($content, $match, $tildaTag)
    {
        // Parsing tag service parameters: PROJECT, PAGE, HIDEPAGETEMPLATE, MOVERESOURCESTO
        $params = array();

        $explodedParams = preg_split("~(\s|&nbsp;)+~u", $match);
        foreach ($explodedParams as $value) {
            $keyValue = explode('=', $value, 2);
            $key = str_replace("amp;", "", $keyValue[0]);
            $params[$key] = $keyValue[1] ?? '';
        }

        $pageId = (int)($params['PAGE'] ?? 0);

        if (
            !empty($params['HIDEPAGETEMPLATE']) &&
            $params['HIDEPAGETEMPLATE'] === "Y"
        ) {
            // Case: Don't display site template
            Logger::debug('Replacing tag', ['pageId' => $pageId, 'mode' => 'HIDEPAGETEMPLATE']);

            $content = Common::getPageFullContent($pageId);
        } else {
            $moveTarget = MoveResourcesTarget::fromMixed($params['MOVERESOURCESTO'] ?? '');
            if (MoveResourcesTarget::shouldMove($moveTarget)) {
                // Case: Display site template case + replace Tilda tag + move Tilda assets
                Logger::debug('Replacing tag', ['pageId' => $pageId, 'mode' => 'MOVERESOURCESTO=' . $moveTarget]);

                $parts = Common::getPageParts($pageId);

                // Insert Tilda assets to the place defined by the tag (head/body end)
                $content = MoveResourcesTarget::injectAssets($moveTarget, $content, $parts['assets']);

                // Replace Tilda tag with content
                $content = str_replace($tildaTag, $parts['html'], $content);
            } else {
                // Case: Display site template case + replace Tilda tag
                Logger::debug('Replacing tag', ['pageId' => $pageId, 'mode' => 'default']);

                $html = Common::getPageContent($pageId, $params);

                $content = str_replace($tildaTag, $html, $content);
            }
        }

        return $content;
    }

    /**
     * Вырезает теги `[UPLABTILDA ...]` из полей `TITLE` и `BODY` перед
     * индексацией контента инфоблоков поиском, чтобы они не попадали в индекс.
     *
     * Предназначен для обработчика события поиска (`OnSearchGetContent`/
     * `BeforeIndex`).
     *
     * @param array $arFields Поля индексируемого элемента.
     * @return array Поля с очищенными от тегов `TITLE` и `BODY`.
     */
    static function removeFromIndex($arFields)
    {
        if ($arFields["MODULE_ID"] === "iblock") {
            $arFields["TITLE"] = preg_replace(
                "/\[(UPLABTILDA [^\]]+)\]/",
                "",
                $arFields["TITLE"]
            );

            $arFields["BODY"] = preg_replace(
                "/\[(UPLABTILDA [^\]]+)\]/",
                "",
                $arFields["BODY"]
            );
        }

        return $arFields;
    }
}
