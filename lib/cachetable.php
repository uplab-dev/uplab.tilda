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

/*
 * Псевдоним класса для обратной совместимости с версиями до 3.3.0, где
 * ORM-сущность реестра страниц называлась \Uplab\Tilda\CacheTable.
 *
 * @deprecated 3.3.0 Используйте \Uplab\Tilda\Model\CacheTable.
 */

if (!class_exists(\Uplab\Tilda\CacheTable::class, false)) {
    class_alias(\Uplab\Tilda\Model\CacheTable::class, \Uplab\Tilda\CacheTable::class);
}
