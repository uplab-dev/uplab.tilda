<?php

use Bitrix\Main\Loader;

/**
 * @property array arResult
 * @property array arParams
 * @method includeComponentTemplate
 */
class UplabTildaComponent extends CBitrixComponent
{
    /**
     * Допустимые цели выноса ресурсов Tilda.
     * Должны совпадать со значениями, которые понимает Uplab\Tilda\Replace::tagReplace().
     */
    private const ALLOWED_MOVE_TARGETS = ['', 'HEADEND', 'BODYEND'];

    public function onPrepareComponentParams($params)
    {
        $params["STOP_CACHE"] = ($params["STOP_CACHE"] ?? '') === "Y" ? "Y" : "N";
        $params["PAGE"] = (int)($params["PAGE"] ?? 0);
        $params["PROJECT"] = (int)($params["PROJECT"] ?? 0);
        $params["MOVE_RESOURCES_TO"] = $this->normalizeMoveTarget($params["MOVE_RESOURCES_TO"] ?? '');

        return $params;
    }

    protected function getResult()
    {
        if (!Loader::includeModule("uplab.tilda")) {
            return;
        }

        $this->arResult = [];

        if ($this->arParams["PROJECT"] > 0 && $this->arParams["PAGE"] > 0) {
            $tagParts = [
                'PROJECT=' . $this->arParams["PROJECT"],
                'PAGE=' . $this->arParams["PAGE"],
                'HIDEPAGETEMPLATE=' . $this->arParams["STOP_CACHE"]
            ];

            $moveTarget = $this->normalizeMoveTarget($this->arParams["MOVE_RESOURCES_TO"] ?? '');

            if ($moveTarget !== '') {
                $tagParts[] = 'MOVERESOURCESTO=' . $moveTarget;
            }

            $this->arResult["HTML"] = '[UPLABTILDA ' . implode(' ', $tagParts) . ']';
        }
    }

    /**
     * Приводит значение MOVE_RESOURCES_TO к одному из допустимых;
     * всё неизвестное превращается в пустую строку (ресурсы не выносятся).
     */
    private function normalizeMoveTarget($value): string
    {
        $value = strtoupper(trim((string)$value));

        return in_array($value, self::ALLOWED_MOVE_TARGETS, true) ? $value : '';
    }

    public function executeComponent()
    {
        $this->getResult();
        $this->includeComponentTemplate();
    }
}
