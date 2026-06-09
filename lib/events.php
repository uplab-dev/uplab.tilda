<?php

namespace Uplab\Tilda;

use CJSCore;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Обработчики событий ядра Битрикс, регистрируемые модулем.
 *
 * Подключает ресурсы визуального редактора для вставки тега Tilda и
 * регистрирует тип события в журнале аудита.
 *
 * @package Uplab\Tilda
 */
class Events
{
	/**
	 * Регистрирует и инициализирует JS/CSS-расширение визуального редактора
	 * (`uplab_tilda_visual`) перед запуском HTML-редактора.
	 *
	 * Обработчик события `fileman:OnBeforeHTMLEditorScriptRuns`.
	 *
	 * @return void
	 */
	public static function beforeHTMLEditorScriptRuns()
	{
		CJSCore::RegisterExt(
			'uplab_tilda_visual',
			array(
				'js'   => array(
					'/bitrix/js/uplab.tilda/visual.js',
				),
				'css'  => array(
					'/bitrix/js/fileman/comp_params_manager/component_params_manager.css',
					'/bitrix/css/uplab.tilda/visual.css'
				),
				'lang' => '/bitrix/modules/uplab.tilda/lang/' . LANGUAGE_ID . '/install/js/visual.php',
			)
		);

		CJSCore::Init(
			array(
				'uplab_tilda_visual'
			)
		);
	}

	/**
	 * Регистрирует тип события `UPLAB_TILDA_DATA` в журнале аудита Битрикс.
	 *
	 * Обработчик события `main:OnEventLogGetAuditTypes`.
	 *
	 * @return array Массив вида [код типа события => название].
	 */
	public static function onEventLogGetAuditTypes()
	{
		return array('UPLAB_TILDA_DATA' => Loc::getMessage('uplab.tilda_ERROR_DATA'));
	}
}