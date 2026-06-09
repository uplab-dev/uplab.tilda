<?php

namespace Uplab\Tilda;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Вспомогательные утилиты модуля: работа с кодировками, постобработка
 * контента, вызов кастомного события и уведомления об ошибках.
 *
 * @package Uplab\Tilda
 */
class Helper
{
    /**
     * Приводит строку из UTF-8 к кодировке windows-1251, если сайт работает
     * не в UTF-8 (константа `BX_UTF` не определена).
     *
     * @param string $string Исходная строка в UTF-8.
     * @param bool $skip Если true — конвертация пропускается, строка возвращается как есть.
     * @return string Строка в кодировке сайта.
     */
    public static function convert2Win1251($string, $skip = false)
    {
        return (!defined("BX_UTF") && !$skip) ?
            iconv("UTF-8", "windows-1251//IGNORE", $string) :
            $string;
    }

    /**
     * Удаляет из контента фрагменты, обёрнутые в маркеры
     * `<!--UTilda-->...<!--EndUTilda-->`, заменяя их на
     * `<!--TILDA_COMMENT_REMOVED-->`.
     *
     * @param string $content Контент (передаётся по ссылке и изменяется).
     * @return void
     */
    public static function removeCommentedCode(&$content)
    {
        $pattern = "~<!--UTilda-->([\s\S]*?)<!--EndUTilda-->~im";
        $matches = [];

        preg_match_all(
            $pattern,
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $content = str_replace($match[0], "<!--TILDA_COMMENT_REMOVED-->", $content);
        }
    }

    /**
     * Запускает обработчики кастомного события `uplab.tilda:onBeforeContentReplace`,
     * при их наличии, передавая контент по ссылке для модификации.
     *
     * @param string $content Контент страницы Tilda (передаётся по ссылке).
     * @return void
     */
    public static function checkEventBeforeContentReplace(&$content)
    {
        $rsEvents = GetModuleEvents("uplab.tilda", "onBeforeContentReplace");

        while ($arEvent = $rsEvents->Fetch()) {
            ExecuteModuleEventEx($arEvent, [&$content]);
        }
    }

    /**
     * Регистрирует ошибку модуля: показывает уведомление в админке
     * ({@see \CAdminNotify}) и пишет запись в журнал событий
     * ({@see \CEventLog}, тип `UPLAB_TILDA_DATA`).
     *
     * @param string $message Текст ошибки.
     * @return void
     */
    public static function notifyError($message)
    {
        // Показать уведомление
        \CAdminNotify::Add([
            'NOTIFY_TYPE'  => \CAdminNotify::TYPE_ERROR,
            'MESSAGE'      => Loc::getMessage('uplab.tilda_ERROR_REQUEST', ['#MESSAGE#' => $message]),
            'TAG'          => Common::$module_id . 'error' . md5($message),
            'MODULE_ID'    => Common::$module_id,
            'ENABLE_CLOSE' => 'Y'
        ]);

        // Записать в журнал
        \CEventLog::Add([
            'SEVERITY'      => 'ERROR',
            'AUDIT_TYPE_ID' => 'UPLAB_TILDA_DATA',
            'MODULE_ID'     => Common::$module_id,
            'ITEM_ID'       => Common::$module_id,
            'DESCRIPTION'   => $message
        ]);
    }
}