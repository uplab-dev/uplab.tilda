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
 * Куда выносить ресурсы (CSS/JS) страницы Tilda при вставке с шаблоном сайта.
 *
 * Единый источник истины для значения тега `MOVERESOURCESTO=...`.
 * Используется во всех местах, где это значение читается, валидируется или
 * выводится в выпадающем списке:
 *  - {@see \Uplab\Tilda\Replace::replaceContent()} — выбор места вставки ассетов;
 *  - компонент `uplab:tilda` (`class.php`, `.parameters.php`) — параметр `MOVE_RESOURCES_TO`;
 *  - админ-попап `admin/editor_popup.php` — выпадающий список и сборка тега.
 *
 * Реализовано как «эмулированный enum» (final-класс с константами и
 * статическими методами), а не как PHP 8.1 `enum`, чтобы модуль продолжал
 * работать на PHP 7.4 — у части клиентов ещё используется эта версия.
 * Все допустимые значения объявлены здесь константами; чтобы добавить новый
 * режим выноса, добавьте константу, ветку в {@see self::injectAssets()} и
 * строку в {@see self::langSuffixes()}.
 *
 * @package Uplab\Tilda\Enum
 */
final class MoveResourcesTarget
{
    /** Не выносить: ресурсы остаются в месте вставки тега. */
    const NONE = '';

    /** Вынести в конец секции `<head>`. */
    const HEAD_END = 'HEADEND';

    /** Вынести в конец секции `<body>`. */
    const BODY_END = 'BODYEND';

    /**
     * Класс — только набор констант и статических хелперов, инстансы не нужны.
     */
    private function __construct()
    {
    }

    /**
     * Безопасно приводит произвольное (пользовательское) значение к одной из
     * констант класса.
     *
     * Регистр и окружающие пробелы игнорируются; неизвестное значение
     * превращается в {@see self::NONE} (ресурсы не выносятся).
     *
     * @param mixed $value Сырое значение из тега, параметра компонента или формы.
     * @return string Одно из значений: self::NONE | self::HEAD_END | self::BODY_END.
     */
    public static function fromMixed($value): string
    {
        $normalized = strtoupper(trim((string)$value));

        return in_array($normalized, self::all(), true) ? $normalized : self::NONE;
    }

    /**
     * Нужно ли выносить ресурсы (значение отличается от {@see self::NONE}).
     *
     * @param mixed $value Сырое или уже нормализованное значение.
     */
    public static function shouldMove($value): bool
    {
        return self::fromMixed($value) !== self::NONE;
    }

    /**
     * Вставляет ассеты Tilda в нужное место HTML-страницы согласно режиму.
     * Для {@see self::NONE} контент возвращается без изменений.
     *
     * @param mixed  $value   Режим выноса (сырое или нормализованное значение).
     * @param string $content Полный HTML страницы.
     * @param string $assets  Блок ресурсов Tilda (<link>/<style>/<script>).
     * @return string HTML с вставленными ассетами.
     */
    public static function injectAssets($value, string $content, string $assets): string
    {
        switch (self::fromMixed($value)) {
            case self::HEAD_END:
                return str_replace('</head>', $assets . '</head>', $content);
            case self::BODY_END:
                return str_replace('</body>', $assets . '</body>', $content);
            default:
                return $content;
        }
    }

    /**
     * Все допустимые значения в порядке отображения.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [self::NONE, self::HEAD_END, self::BODY_END];
    }

    /**
     * Карта «значение тега => суффикс кода языковой фразы» для построения
     * выпадающих списков. Сами фразы лежат в языковых файлах компонента/попапа
     * со своими префиксами, поэтому суффикс кода берётся отсюда, а префикс
     * задаёт вызывающая сторона.
     *
     * Пример: `Loc::getMessage($prefix . $suffix)`.
     *
     * @return array<string, string> value => суффикс кода фразы
     */
    public static function langSuffixes(): array
    {
        return [
            self::NONE     => 'MOVE_TILDA_ASSETS_NONE',
            self::HEAD_END => 'MOVE_TILDA_ASSETS_HEADEND',
            self::BODY_END => 'MOVE_TILDA_ASSETS_BODYEND',
        ];
    }
}
