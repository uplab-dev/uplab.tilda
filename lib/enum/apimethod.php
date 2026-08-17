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

namespace Uplab\Tilda\Enum;

/**
 * Допустимые методы Tilda API.
 *
 * Единый источник истины для строковых имён методов. Используется везде,
 * где метод передаётся строкой: {@see \Uplab\Tilda\Request::getData()},
 * {@see \Uplab\Tilda\Request::checkConnection()}.
 *
 * Реализовано как «эмулированный enum» (final-класс с константами),
 * а не PHP 8.1 `enum`, для совместимости с PHP 7.4.
 *
 * @package Uplab\Tilda\Enum
 */
final class ApiMethod
{
    const PROJECTS_LIST = 'getprojectslist';
    const PAGES_LIST    = 'getpageslist';
    const PAGE_FULL     = 'getpagefull';

    private function __construct()
    {
    }

    /**
     * Все допустимые методы API.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [self::PROJECTS_LIST, self::PAGES_LIST, self::PAGE_FULL];
    }

    /**
     * Проверяет, входит ли значение в список допустимых методов.
     *
     * @param string $method
     * @return bool
     */
    public static function isValid($method): bool
    {
        return in_array($method, self::all(), true);
    }
}
