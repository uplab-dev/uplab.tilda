<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

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

        if (!$this->isVersionD7()) {
            $APPLICATION->ThrowException(Loc::getMessage("{$this->MODULE_ID}_MODULE_NO_D7_ERROR"));

            return false;
        }

        $this->installEvents();
        $this->installFiles();
        $this->installTables();

        ModuleManager::registerModule($this->MODULE_ID);

        return null;
    }

    public function doUninstall()
    {
        $this->uninstallEvents();
        $this->uninstallFiles();
        $this->uninstallTables();

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function installTables()
    {
        $connection = Application::getConnection();

        $drop = "DROP TABLE IF EXISTS `tilda_pages_cache`;";
        $create = "CREATE TABLE `tilda_pages_cache` (
`TAG` CHAR(32) NOT NULL,
`NAME` TEXT,
`DATE` DATETIME,
PRIMARY KEY (`TAG`)
);";

        $connection->query($drop);
        $connection->query($create);
    }

    public function uninstallTables()
    {
        $connection = Application::getConnection();

        $drop = "DROP TABLE IF EXISTS `tilda_pages_cache`;";

        $connection->query($drop);
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

    public function uninstallFiles()
    {
        DeleteDirFilesEx("{$_SERVER["DOCUMENT_ROOT"]}/bitrix/components/uplab/tilda");
        DeleteDirFilesEx("{$_SERVER["DOCUMENT_ROOT"]}/bitrix/cache_tilda");

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
        $eventManager = \Bitrix\Main\EventManager::getInstance();

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

    public function isVersionD7()
    {
        return CheckVersion(
            ModuleManager::getVersion("main"),
            "14.00.00"
        );
    }

    public function getPath()
    {
        return $_SERVER["DOCUMENT_ROOT"] . getLocalPath("modules/$this->MODULE_ID");
    }
}