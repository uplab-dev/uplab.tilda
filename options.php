<?php

/** @global CMain $APPLICATION */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;

$request = HttpApplication::getInstance()->getContext()->getRequest();
$moduleId = "uplab.tilda";

// Страницу подключает /bitrix/admin/settings.php, который уже проверил операцию
// view_other_settings и право на модуль >= R. Дублируем проверки явно: без
// загруженного модуля упадут вызовы его классов ниже, а сохранение опций должно
// требовать право на изменение, которого ядро на этом этапе не требует.
if (!Loader::includeModule($moduleId)) {
    return;
}

$moduleRight = $APPLICATION->GetGroupRight($moduleId);

if ($moduleRight < 'R') {
    return;
}

$logDir = \Uplab\Tilda\Diag\Logger::getLogDir();
// Модуль пишет в этот каталог только свои tilda_*.log — всё остальное показываем,
// чтобы посторонние файлы не лежали там незамеченными.
$foreignLogFiles = \Uplab\Tilda\Diag\Logger::findForeignFiles();
// До 3.3.2 журнал вёлся посуточными файлами, и они не удалялись. Модуль их не
// трогает (журнал может понадобиться для разбора инцидента), поэтому показываем
// занятое ими место — удалить можно кнопкой «Очистить логи».
$legacyLogs = \Uplab\Tilda\Diag\Logger::findLegacyLogs();

// Если сохранённый адрес API отклонён проверкой, модуль молча работает через
// адрес по умолчанию — предупреждаем об этом прямо на странице настроек.
$storedApiUrl    = trim((string)Option::get($moduleId, 'UPT_API_URL', ''));
$effectiveApiUrl = \Uplab\Tilda\Request::resolveApiUrl();
$apiUrlError     = ($storedApiUrl !== '' && $storedApiUrl !== $effectiveApiUrl)
    ? Loc::getMessage('uplab.tilda_API_URL_INVALID', ['#URL#' => $effectiveApiUrl])
    : '';

$aTabs = [
    [
        'DIV'     => 'tilda_options',
        'TAB'     => Loc::getMessage('uplab.tilda_SETTINGS_TAB_NAME'),
        'OPTIONS' => [
            Loc::getMessage('uplab.tilda_SETTINGS_TAB_NAME'),
            [
                'UPT_PUBLIC_KEY',
                Loc::getMessage('uplab.tilda_PUBLIC_KEY'),
                '',
                ['text', 30],
            ],
            [
                'UPT_SECRET_KEY',
                Loc::getMessage('uplab.tilda_SECRET_KEY'),
                '',
                ['text', 10],
            ],
            [
                '',
                '',
                '<button type="button" id="uTildaCheckConnectionBtn" class="ui-btn ui-btn-primary ui-btn-sm"'
                    . ' onclick="uTildaCheckConnection('
                    . htmlspecialcharsbx(json_encode(Loc::getMessage('uplab.tilda_CHECK_CONN_CHECKING')))
                    . ', '
                    . htmlspecialcharsbx(json_encode(Loc::getMessage('uplab.tilda_CHECK_CONN_EMPTY_KEYS')))
                    . ')">'
                    . htmlspecialcharsbx(Loc::getMessage('uplab.tilda_CHECK_CONNECTION'))
                    . '</button>',
                ['statichtml'],
            ],
            Loc::getMessage('uplab.tilda_BASE_SETTINGS'),
            [
                'UPT_API_URL',
                Loc::getMessage('uplab.tilda_API_URL'),
                \Uplab\Tilda\Common::DEFAULT_API_URL,
                ['text', 50],
            ],
            [
                'UPT_CURLOPT_TIMEOUT',
                Loc::getMessage('uplab.tilda_TIMEOUT'),
                15,
                ['text', 10],
            ],
            [
                'UPT_CURLOPT_CONNECTTIMEOUT',
                Loc::getMessage('uplab.tilda_CONNECTTIMEOUT'),
                15,
                ['text', 10],
            ],
            [
                'UPT_LIST_CACHE_TTL',
                Loc::getMessage('uplab.tilda_LIST_CACHE_TTL'),
                \Uplab\Tilda\Service\Cache::DEFAULT_LIST_TTL,
                ['text', 10],
            ],
            Loc::getMessage('uplab.tilda_RESILIENCE_SECTION'),
            [
                'UPT_REQUEST_ATTEMPTS',
                Loc::getMessage('uplab.tilda_ATTEMPTS'),
                \Uplab\Tilda\Request::DEFAULT_ATTEMPTS,
                ['text', 10],
            ],
            [
                'UPT_STALL_TIMEOUT',
                Loc::getMessage('uplab.tilda_STALL_TIMEOUT'),
                \Uplab\Tilda\Request::DEFAULT_STALL_TIMEOUT,
                ['text', 10],
            ],
            [
                'UPT_STALE_ON_ERROR',
                Loc::getMessage('uplab.tilda_STALE_ON_ERROR'),
                'N',
                ['checkbox'],
            ],
            [
                'note' => Loc::getMessage('uplab.tilda_STALE_NOTE'),
            ],
        ],
    ],
    [
        'DIV'     => 'tilda_logging',
        'TAB'     => Loc::getMessage('uplab.tilda_LOGGING_TAB_NAME'),
        'TITLE'   => Loc::getMessage('uplab.tilda_LOGGING_TAB_NAME'),
        'OPTIONS' => [
            Loc::getMessage('uplab.tilda_LOGGING_SECTION'),
            [
                'UPT_LOG_ENABLED',
                Loc::getMessage('uplab.tilda_LOG_ENABLED'),
                'N',
                ['checkbox'],
            ],
            [
                'UPT_LOG_LEVEL',
                Loc::getMessage('uplab.tilda_LOG_LEVEL'),
                'error',
                ['selectbox', [
                    'debug'   => Loc::getMessage('uplab.tilda_LOG_LEVEL_DEBUG'),
                    'info'    => Loc::getMessage('uplab.tilda_LOG_LEVEL_INFO'),
                    'warning' => Loc::getMessage('uplab.tilda_LOG_LEVEL_WARNING'),
                    'error'   => Loc::getMessage('uplab.tilda_LOG_LEVEL_ERROR'),
                ]],
            ],
            [
                'UPT_LOG_DIR',
                Loc::getMessage('uplab.tilda_LOG_DIR'),
                '',
                ['text', 50],
            ],
            [
                'note' => Loc::getMessage('uplab.tilda_LOG_DIR_LABEL') . ' ' . htmlspecialcharsbx($logDir)
                    . (is_dir($logDir)
                        ? ' &#10003; ' . htmlspecialcharsbx(Loc::getMessage('uplab.tilda_LOG_DIR_EXISTS'))
                        : ' ' . htmlspecialcharsbx(Loc::getMessage('uplab.tilda_LOG_DIR_NOT_EXISTS')))
                    . ($foreignLogFiles
                        ? '<br>' . htmlspecialcharsbx(
                            Loc::getMessage('uplab.tilda_LOG_DIR_FOREIGN', [
                                '#FILES#' => implode(', ', $foreignLogFiles),
                            ])
                        )
                        : '')
                    . ($legacyLogs['count'] > 0
                        ? '<br>' . htmlspecialcharsbx(
                            Loc::getMessage('uplab.tilda_LOG_DIR_LEGACY', [
                                '#COUNT#' => $legacyLogs['count'],
                                '#SIZE#'  => CFile::FormatSize($legacyLogs['size']),
                            ])
                        )
                        : ''),
            ],
            [
                '',
                Loc::getMessage('uplab.tilda_CLEAR_LOGS'),
                '<button type="button" class="ui-btn ui-btn-danger ui-btn-sm"'
                    . ' onclick="uTildaClearLogs('
                    . htmlspecialcharsbx(json_encode(Loc::getMessage('uplab.tilda_CLEAR_LOGS_CONFIRM')))
                    . ')">'
                    . htmlspecialcharsbx(Loc::getMessage('uplab.tilda_CLEAR_LOGS_BTN'))
                    . '</button>',
                ['statichtml'],
            ],
        ],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_REQUEST['save'] ?? '') !== ''
    && $moduleRight >= 'W' && check_bitrix_sessid()) {
    foreach ($aTabs as $aTab) {
        if (!empty($aTab['OPTIONS'])) {
            __AdmSettingsSaveOptions($moduleId, $aTab['OPTIONS']);
        }
    }

    LocalRedirect(
        $APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&mid_menu=1&mid=' . urlencode($moduleId) .
        '&tabControl_active_tab=' . urlencode($_REQUEST['tabControl_active_tab'] ?? '')
    );
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);

if ($apiUrlError !== '') {
    // Текст экранируется самим CAdminMessage (флаг HTML не выставляем).
    CAdminMessage::ShowMessage(['MESSAGE' => $apiUrlError, 'TYPE' => 'ERROR']);
}
?>
<form method='post' action='' name='bootstrap'>
    <?php
    \Bitrix\Main\UI\Extension::load('ui.buttons');

    $tabControl->Begin();

    foreach ($aTabs as $aTab) {
        $tabControl->BeginNextTab();
        if (!empty($aTab['OPTIONS'])) {
            __AdmSettingsDrawList($moduleId, $aTab['OPTIONS']);
        }
    }

    $tabControl->Buttons([
        // При праве только на чтение кнопку не показываем — сохранение всё равно
        // будет отклонено проверкой $moduleRight выше.
        'btnSave'       => $moduleRight >= 'W',
        'btnApply'      => false,
        'btnCancel'     => false,
        'btnSaveAndAdd' => false,
    ]);
    ?>
    <?= bitrix_sessid_post(); ?>
    <?php $tabControl->End(); ?>
</form>
