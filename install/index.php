<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Uplab\Tilda\Model\CacheTable;

Loc::loadMessages(__FILE__);

class uplab_tilda extends CModule
{
    public $MODULE_ID = "uplab.tilda";
    public $MODULE_GROUP_RIGHTS = "Y";
    public function __construct()
    {
        $this->MODULE_NAME = Loc::getMessage("{$this->MODULE_ID}_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("{$this->MODULE_ID}_MODULE_DESC");
        $this->PARTNER_NAME = Loc::getMessage("{$this->MODULE_ID}_PARTNER_NAME");
        $this->PARTNER_URI = Loc::getMessage("{$this->MODULE_ID}_PARTNER_URI");

        $arModuleVersion = [];

        include __DIR__ . "/version.php";

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }
    }

    public function doInstall()
    {
        global $APPLICATION;

        if (!$this->areModuleRequirementsMet()) {
            $APPLICATION->ThrowException(Loc::getMessage("{$this->MODULE_ID}_MODULE_NO_D7_ERROR"));

            return false;
        }

        $this->installEvents();
        $this->installFiles();

        ModuleManager::registerModule($this->MODULE_ID);

        $this->installTables();

        return null;
    }

    public function doUninstall()
    {
        global $APPLICATION, $step;

        if ($APPLICATION->GetGroupRight($this->MODULE_ID) < 'W') {
            return;
        }

        $step = (int)$step;

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage("{$this->MODULE_ID}_UNINSTALL_WIZARD_TITLE"),
                $this->getPath() . '/install/unstep1.php'
            );
        } elseif ($step === 2) {
            if (!check_bitrix_sessid()) {
                $APPLICATION->ThrowException(Loc::getMessage('MAIN_MODULE_SECURITY_ERROR') ?: 'Invalid session token');
                return;
            }

            $this->uninstallEvents();
            $this->uninstallFiles(['delete_cache' => $_REQUEST['delete_cache'] ?? null]);
            $this->uninstallTables(['delete_table' => $_REQUEST['delete_table'] ?? null]);

            if (($_REQUEST['delete_logs'] ?? null) === 'Y' && Loader::includeModule($this->MODULE_ID)) {
                \Uplab\Tilda\Diag\Logger::clearLogs();
            }

            if (($_REQUEST['delete_settings'] ?? null) === 'Y') {
                Option::delete($this->MODULE_ID);
            }

            ModuleManager::unRegisterModule($this->MODULE_ID);
        }
    }

    public function installTables()
    {
        $connection = Application::getConnection();

        if ($connection->isTableExists('tilda_pages_cache')) {
            $connection->dropTable('tilda_pages_cache');
        }

        // Таблицу создаём из ORM-сущности — DDL генерируется ядром под текущую
        // СУБД (MySQL/PostgreSQL), без «сырого» MySQL-специфичного SQL.
        // Модуль на этом этапе ещё не зарегистрирован, поэтому подключаем его
        // явно, чтобы автозагрузился класс CacheTable.
        Loader::includeModule($this->MODULE_ID);

        CacheTable::getEntity()->createDbTable();
    }

    public function uninstallTables($arParams = [])
    {
        if (($arParams['delete_table'] ?? null) !== 'Y') {
            return;
        }

        $connection = Application::getConnection();

        if ($connection->isTableExists('tilda_pages_cache')) {
            $connection->dropTable('tilda_pages_cache');
        }
    }

    public function installFiles()
    {
        CopyDirFiles(
            $this->getPath() . "/install/bitrix/",
            "{$_SERVER["DOCUMENT_ROOT"]}/bitrix/",
            true,
            true
        );

        return true;
    }

    public function installEvents()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->registerEventHandler(
            "fileman",
            "OnBeforeHTMLEditorScriptRuns",
            $this->MODULE_ID,
            "Uplab\Tilda\Events",
            "beforeHTMLEditorScriptRuns"
        );

        $eventManager->registerEventHandler(
            "main",
            "OnEndBufferContent",
            $this->MODULE_ID,
            "Uplab\Tilda\Replace",
            "tagReplace"
        );

        $eventManager->registerEventHandler(
            "search",
            "BeforeIndex",
            $this->MODULE_ID,
            "Uplab\Tilda\Replace",
            "removeFromIndex"
        );

        $eventManager->registerEventHandler(
            "main",
            "OnEventLogGetAuditTypes",
            $this->MODULE_ID,
            "Uplab\Tilda\Events",
            "onEventLogGetAuditTypes"
        );
    }

    public function uninstallFiles($arParams = [])
    {
        DeleteDirFilesEx("{$_SERVER["DOCUMENT_ROOT"]}/bitrix/components/uplab/tilda");

        if (($arParams['delete_cache'] ?? null) === 'Y') {
            DeleteDirFilesEx("{$_SERVER["DOCUMENT_ROOT"]}/bitrix/cache_tilda");
        }

        $file = "{$_SERVER["DOCUMENT_ROOT"]}/upload/tilda_cookie.txt";
        if (\Bitrix\Main\IO\File::isFileExists($file)) {
            \Bitrix\Main\IO\File::deleteFile($file);
        }

        DeleteDirFiles(
            $this->getPath() . '/install/bitrix/',
            $_SERVER["DOCUMENT_ROOT"] . '/bitrix/'
        );

        return true;
    }

    public function uninstallEvents()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->unRegisterEventHandler(
            "fileman",
            "OnBeforeHTMLEditorScriptRuns",
            $this->MODULE_ID,
            "Uplab\Tilda\Events",
            "beforeHTMLEditorScriptRuns"
        );

        $eventManager->unRegisterEventHandler(
            "main",
            "OnEndBufferContent",
            $this->MODULE_ID,
            "Uplab\Tilda\Replace",
            "tagReplace"
        );

        $eventManager->unRegisterEventHandler(
            "search",
            "BeforeIndex",
            $this->MODULE_ID,
            "Uplab\Tilda\Replace",
            "removeFromIndex"
        );

        $eventManager->unRegisterEventHandler(
            "main",
            "OnEventLogGetAuditTypes",
            $this->MODULE_ID,
            "Uplab\Tilda\Events",
            "onEventLogGetAuditTypes"
        );
    }

    public function areModuleRequirementsMet()
    {
        // Требуется ядро >= 21.900.0 (FileLogger)
        return version_compare(
            ModuleManager::getVersion("main"),
            "21.900.0",
            ">="
        );
    }

    public function getPath()
    {
        return $_SERVER["DOCUMENT_ROOT"] . getLocalPath("modules/$this->MODULE_ID");
    }
}