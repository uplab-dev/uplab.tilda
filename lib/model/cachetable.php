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

namespace Uplab\Tilda\Model;

use Bitrix\Main\Entity;

/**
 * ORM-сущность реестра закэшированных страниц Tilda (таблица `tilda_pages_cache`).
 *
 * Хранит соответствие тега кэша странице: заголовок, id страницы и проекта,
 * дату кэширования. Используется классом {@see \Uplab\Tilda\Cache} для ведения
 * реестра и отображения/очистки страниц в разделе администратора.
 *
 * @package Uplab\Tilda\Model
 */
class CacheTable extends Entity\DataManager
{
    /**
     * Возвращает имя таблицы сущности.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'tilda_pages_cache';
    }

    /**
     * Описывает поля сущности:
     * - `TAG` — первичный ключ (md5 URL запроса, 32 символа);
     * - `NAME` — заголовок страницы Tilda;
     * - `PAGE_ID` — идентификатор страницы в Tilda;
     * - `PROJECT_ID` — идентификатор проекта в Tilda;
     * - `DATE` — дата последнего кэширования.
     *
     * @return Entity\Field[]
     * @throws \Bitrix\Main\SystemException
     */
    public static function getMap()
    {
        return [
            (new Entity\StringField('TAG'))
                ->configurePrimary()
                ->configureSize(32),
            (new Entity\TextField('NAME'))
                ->configureNullable(),
            (new Entity\IntegerField('PAGE_ID'))
                ->configureNullable(),
            (new Entity\IntegerField('PROJECT_ID'))
                ->configureNullable(),
            (new Entity\DatetimeField('DATE'))
                ->configureNullable(),
        ];
    }

    /**
     * Добавляет новую запись о странице или обновляет существующую по тегу.
     *
     * @param array $data Данные записи; обязательно содержит ключ `TAG`.
     * @return bool false, если тег не задан, иначе true.
     */
    public static function addOrUpdate($data)
    {
        if (empty($data['TAG'])) {
            return false;
        }

        $row = self::getByPrimary($data['TAG'])->fetch();

        if ($row) {
            self::update($data['TAG'], $data);
        } else {
            self::add($data);
        }

        return true;
    }
}
