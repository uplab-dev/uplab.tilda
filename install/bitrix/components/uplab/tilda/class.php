<?php

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