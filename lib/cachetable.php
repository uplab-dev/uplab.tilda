<?php

namespace Uplab\Tilda;

use Bitrix\Main\Entity;

/**
 * ORM-сущность реестра закэшированных страниц Tilda (таблица
 * `tilda_pages_cache`).
 *
 * Хранит соответствие тега кэша странице (имя и дата кэширования) и
 * используется классом {@see Cache} для ведения списка закэшированных страниц
 * и их отображения/очистки в админке.
 *
 * @package Uplab\Tilda
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
     * Описывает поля сущности: `TAG` (первичный ключ, 32 символа),
     * `NAME` (заголовок страницы) и `DATE` (дата кэширования).
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
            (new Entity\StringField('NAME'))
                ->configureSize(255)
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
        if (!$data['TAG']) {
            return false;
        }

        $row = self::getByPrimary($data['TAG'])->fetch();

        if ($row) {
            CacheTable::update(
                $data['TAG'],
                $data
            );
        } else {
            CacheTable::add($data);
        }

        return true;
    }
}
