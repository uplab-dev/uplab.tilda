<?php

use Bitrix\Main\Loader;
use Uplab\Tilda\Common;

/**
 * @property array arResult
 * @property array arParams
 * @method includeComponentTemplate
 */
class UplabTildaComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($params)
    {
        $params["STOP_CACHE"] = $params["STOP_CACHE"] === "Y" ? "Y" : "N";
        $params["PAGE"] = intval($params["PAGE"]);
        $params["PROJECT"] = intval($params["PROJECT"]);

        return $params;
    }

    protected function getResult()
    {
        if (!Loader::includeModule("uplab.tilda")) {
            return;
        }

        $this->arResult = array();

        $this->arResult["HTML"] = '[UPLABTILDA PROJECT=' . $this->arParams["PROJECT"] .
            ' PAGE=' . $this->arParams["PAGE"] .
            ' HIDEPAGETEMPLATE=' . $this->arParams["STOP_CACHE"] .
            ' MOVERESOURCESTO=' . $this->arParams["MOVE_RESOURCES_TO"] .
            ']';
    }

    public function executeComponent()
    {
        $this->getResult();
        $this->includeComponentTemplate();
    }
}