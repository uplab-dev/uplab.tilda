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

use Bitrix\Main\Loader;
use Uplab\Tilda\Enum\MoveResourcesTarget;

/**
 * @property array arResult
 * @property array arParams
 * @method includeComponentTemplate
 */
class UplabTildaComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($params)
    {
        $params["STOP_CACHE"] = ($params["STOP_CACHE"] ?? '') === "Y" ? "Y" : "N";
        $params["PAGE"] = (int)($params["PAGE"] ?? 0);
        $params["PROJECT"] = (int)($params["PROJECT"] ?? 0);

        // Нормализуем цель выноса ресурсов через единый enum модуля.
        $params["MOVE_RESOURCES_TO"] = Loader::includeModule('uplab.tilda')
            ? MoveResourcesTarget::fromMixed($params["MOVE_RESOURCES_TO"] ?? '')
            : '';

        return $params;
    }

    protected function getResult()
    {
        $this->arResult = [];

        if (!Loader::includeModule("uplab.tilda")) {
            return;
        }

        if ($this->arParams["PROJECT"] > 0 && $this->arParams["PAGE"] > 0) {
            $tagParts = [
                'PROJECT=' . $this->arParams["PROJECT"],
                'PAGE=' . $this->arParams["PAGE"],
                'HIDEPAGETEMPLATE=' . $this->arParams["STOP_CACHE"]
            ];

            if ($this->arParams["MOVE_RESOURCES_TO"] !== '') {
                $tagParts[] = 'MOVERESOURCESTO=' . $this->arParams["MOVE_RESOURCES_TO"];
            }

            $this->arResult["HTML"] = '[UPLABTILDA ' . implode(' ', $tagParts) . ']';
        }
    }

    public function executeComponent()
    {
        $this->getResult();

        $this->includeComponentTemplate();
    }
}