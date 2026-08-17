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

namespace Uplab\Tilda\Diag;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\FileLogger;

/**
 * Файловый логгер модуля uplab.tilda поверх {@see \Bitrix\Main\Diag\FileLogger}.
 *
 * Управляется опциями модуля:
 * - `UPT_LOG_ENABLED` (`Y`/`N`) — включить/выключить запись;
 * - `UPT_LOG_LEVEL` (`debug`/`info`/`warning`/`error`) — минимальный уровень;
 * - `UPT_LOG_DIR` — каталог логов (см. {@see Logger::getLogDir()}).
 *
 * Если каталог не задан, логи пишутся в каталог самого модуля —
 * `{DOCUMENT_ROOT}/bitrix/modules/uplab.tilda/logs/` либо
 * `{DOCUMENT_ROOT}/local/modules/uplab.tilda/logs/`, в зависимости от того, куда
 * установлен модуль (определяется через `getLocalPath()`). Оба каталога закрыты
 * от HTTP штатными правилами Битрикса.
 *
 * Файлы — суточные, вида `tilda_{salt}_{date}.log`. Имя содержит случайную соль
 * (32 hex-символа), генерируемую один раз и хранящуюся в опции `UPT_LOG_SALT`, —
 * это делает URL лога непредсказуемым при прямом переборе, если каталог всё же
 * доступен по HTTP. Каталог внутри DOCUMENT_ROOT дополнительно закрывается
 * `.htaccess` (Apache 2.2 и 2.4); для nginx правило приводится в `DEVELOPER.md`.
 * Ключи API в URL маскируются методом {@see Logger::maskUrl()} перед любой записью.
 *
 * @package Uplab\Tilda\Diag
 */
class Logger
{
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /** Разделители блока с телом ответа Tilda в журнале. */
    const BODY_START = '--- body ---';
    const BODY_END   = '--- end body ---';

    /**
     * Предельный размер файла журнала (32 МБ). При превышении ядро копирует
     * файл в `*.log.old` и начинает новый. Штатный лимит FileLogger — 1 МБ,
     * его не хватает: один ответ `getpagefull` занимает сотни килобайт.
     */
    const MAX_FILE_SIZE = 33554432;

    /** @var array<string, int> Приоритеты уровней — для сравнения с минимальным. */
    private static $priorities = [
        self::LEVEL_DEBUG   => 0,
        self::LEVEL_INFO    => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR   => 3,
    ];

    /** @var bool|null Кэш: включён ли логгер (из опции `UPT_LOG_ENABLED`). */
    private static $enabled = null;

    /** @var string|null Кэш: минимальный уровень (из опции `UPT_LOG_LEVEL`). */
    private static $minLevel = null;

    /** @var FileLogger|null Экземпляр FileLogger для текущего дня. */
    private static $fileLogger = null;

    /** @var string Дата, под которую создан {@see $fileLogger} (формат Y-m-d). */
    private static $fileLoggerDate = '';

    /** @var string|null Кэш: случайная соль имени файла (из опции `UPT_LOG_SALT`). */
    private static $salt = null;

    /** @var string|null Кэш: абсолютный путь к каталогу логов (со слэшем на конце). */
    private static $logDir = null;

    /**
     * Записывает сообщение уровня DEBUG.
     *
     * @param string $message Текст сообщения.
     * @param array  $context Дополнительный контекст (ключ→значение).
     */
    public static function debug($message, array $context = [])
    {
        self::write(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Записывает сообщение уровня INFO.
     *
     * @param string $message Текст сообщения.
     * @param array  $context Дополнительный контекст (ключ→значение).
     */
    public static function info($message, array $context = [])
    {
        self::write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Записывает сообщение уровня WARNING.
     *
     * @param string $message Текст сообщения.
     * @param array  $context Дополнительный контекст (ключ→значение).
     */
    public static function warning($message, array $context = [])
    {
        self::write(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Записывает сообщение уровня ERROR.
     *
     * @param string $message Текст сообщения.
     * @param array  $context Дополнительный контекст (ключ→значение).
     */
    public static function error($message, array $context = [])
    {
        self::write(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Маскирует ключи Tilda API в строке URL.
     *
     * Секретный ключ скрывается целиком, публичный — частично (видны первые и
     * последние 4 символа), чтобы по логу можно было понять, каким ключом шёл
     * запрос, но нельзя было им воспользоваться. Вызывается перед любым
     * логированием URL.
     *
     * @param string $url Исходный URL.
     * @return string URL с замаскированными ключами.
     */
    public static function maskUrl($url)
    {
        $url = preg_replace('/(secretkey=)[^&]+/i', '${1}***', (string)$url);

        return preg_replace_callback(
            '/(publickey=)([^&]+)/i',
            function ($matches) {
                return $matches[1] . self::maskKey($matches[2]);
            },
            $url
        );
    }

    /**
     * Сбрасывает кэшированное состояние логгера (настройки и экземпляр FileLogger).
     *
     * Необходимо вызвать после программного изменения опций `UPT_LOG_*`
     * в рамках одного запроса (например, в тестах).
     */
    public static function reset()
    {
        self::$enabled        = null;
        self::$minLevel       = null;
        self::$fileLogger     = null;
        self::$fileLoggerDate = '';
        self::$salt           = null;
        self::$logDir         = null;
    }

    /**
     * Возвращает абсолютный путь к каталогу лог-файлов (со слэшем на конце).
     *
     * Каталог задаётся одной опцией `UPT_LOG_DIR`, а как её трактовать —
     * видно по самому значению:
     * - пусто — подкаталог `logs/` внутри установленного модуля
     *   (`/bitrix/modules/...` либо `/local/modules/...`);
     * - без ведущего слэша (`upload/tilda_logs`) — путь от корня сайта;
     * - с ведущим слэшем или с буквой диска (`/var/log/tilda`, `D:/logs`) —
     *   абсолютный путь в файловой системе хостинга.
     *
     * Значение игнорируется (используется каталог модуля), если содержит `..`
     * либо каталог заведомо непригоден — не существует и не может быть создан
     * из-за прав. Фактический путь показывается на странице настроек модуля.
     *
     * @return string Абсолютный путь, например `/var/www/site/bitrix/modules/uplab.tilda/logs/`.
     */
    public static function getLogDir()
    {
        if (self::$logDir !== null) {
            return self::$logDir;
        }

        $customDir = str_replace('\\', '/', trim((string)Option::get('uplab.tilda', 'UPT_LOG_DIR', '')));
        $dir       = '';

        if ($customDir !== '' && strpos($customDir, '..') === false) {
            $dir = self::isAbsolutePath($customDir)
                ? rtrim($customDir, '/')
                : rtrim(Application::getDocumentRoot(), '/') . '/' . trim($customDir, '/');

            if (!self::isDirUsable($dir)) {
                $dir = '';
            }
        }

        if ($dir === '') {
            $dir = self::getModuleLogDir();
        }

        self::$logDir = $dir . '/';

        return self::$logDir;
    }

    /**
     * Удаляет все файлы логов из каталога.
     *
     * @return int Количество удалённых файлов.
     */
    public static function clearLogs()
    {
        $logDir = self::getLogDir();

        if (!is_dir($logDir)) {
            return 0;
        }

        $count = 0;
        // Маска с хвостом: FileLogger при превышении размера копирует журнал
        // в файл с суффиксом .old — его тоже нужно удалять.
        $files = glob($logDir . 'tilda_*.log*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file) && @unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    // -------------------------------------------------------------------------

    /**
     * Каталог `logs/` внутри установленного модуля. Модуль может лежать как в
     * `/bitrix/modules/`, так и в `/local/modules/` (в том числе при установке
     * через composer), поэтому реальный путь берём у ядра.
     */
    private static function getModuleLogDir()
    {
        $localPath = getLocalPath('modules/uplab.tilda');

        if (!is_string($localPath) || $localPath === '') {
            $localPath = '/bitrix/modules/uplab.tilda';
        }

        return rtrim(Application::getDocumentRoot(), '/') . $localPath . '/logs';
    }

    /**
     * Закрывает каталог логов от HTTP-доступа, если он лежит внутри
     * DOCUMENT_ROOT.
     *
     * Помимо запрета доступа (директивы даны и для Apache 2.4 с `mod_authz_core`,
     * и для 2.2 — на 2.4 старый синтаксис работает только с `mod_access_compat`)
     * скрипты в каталоге обезвреживаются так же, как это делает сам Битрикс
     * в `/upload/.htaccess`: обработчик подменяется на `text/plain`, а движок PHP
     * выключается. Это защита в глубину — если в каталог когда-либо попадёт
     * посторонний `.php`, выполнить его через веб не выйдет.
     *
     * Файл перезаписывается при расхождении с эталоном, чтобы обновление модуля
     * обновляло и защиту. Для nginx правило добавляется в конфиг вручную
     * (см. `DEVELOPER.md`).
     */
    private static function protectLogDir($logDir)
    {
        $documentRoot = rtrim(str_replace('\\', '/', Application::getDocumentRoot()), '/');
        $normalizedDir = rtrim(str_replace('\\', '/', $logDir), '/');

        if (strncmp($normalizedDir, $documentRoot . '/', strlen($documentRoot) + 1) !== 0) {
            return;
        }

        $htaccess = $normalizedDir . '/.htaccess';
        $content = "<IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n"
            . "    Order deny,allow\n"
            . "    Deny from all\n"
            . "</IfModule>\n"
            . "<IfModule mod_mime.c>\n"
            . "    <Files ~ \"\\.(php|php3|php4|php5|php6|php7|php8|phtm|phtml|phar|pl|asp|aspx|cgi|dll|exe|shtm|shtml|fcg|fcgi|fpl|asmx|pht|py|psp|rb|var)$\">\n"
            . "        SetHandler text/plain\n"
            . "        ForceType text/plain\n"
            . "    </Files>\n"
            . "</IfModule>\n"
            . "<IfModule mod_php.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php7.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php8.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n";

        if (!file_exists($htaccess) || @file_get_contents($htaccess) !== $content) {
            @file_put_contents($htaccess, $content);
        }

        // Слой ядра Битрикса: запрет на каталог для всех групп. Работает для
        // запросов, которые обрабатывает ядро; статику веб-сервер отдаёт мимо
        // него, поэтому .access.php дополняет .htaccess, а не заменяет его.
        $accessFile = $normalizedDir . '/.access.php';
        $accessContent = "<?php\n"
            . "// Каталог логов модуля uplab.tilda — доступ закрыт для всех групп.\n"
            . "\$PERM[\"/\"][\"*\"] = \"D\";\n";

        if (!file_exists($accessFile) || @file_get_contents($accessFile) !== $accessContent) {
            @file_put_contents($accessFile, $accessContent);
        }
    }

    /**
     * Возвращает имена файлов каталога логов, которые модуль не создавал.
     *
     * Модуль пишет только `tilda_*.log` (плюс `*.log.old` от ротации),
     * `.htaccess` и `.access.php`. Всё остальное — повод посмотреть, откуда оно
     * взялось, поэтому список показывается на странице настроек.
     *
     * @param int $limit Сколько имён вернуть максимум.
     * @return string[]
     */
    public static function findForeignFiles($limit = 5)
    {
        $logDir = self::getLogDir();

        if (!is_dir($logDir)) {
            return [];
        }

        $entries = @scandir($logDir);

        if ($entries === false) {
            return [];
        }

        $foreign = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === '.access.php') {
                continue;
            }

            if (preg_match('/^tilda_[a-f0-9]{32}_\d{4}-\d{2}-\d{2}\.log(\.old)?$/i', $entry)) {
                continue;
            }

            $foreign[] = $entry;

            if (count($foreign) >= $limit) {
                break;
            }
        }

        return $foreign;
    }

    private static function isAbsolutePath($path)
    {
        return strncmp($path, '/', 1) === 0 || preg_match('#^[A-Za-z]:/#', $path) === 1;
    }

    /**
     * Проверяет, что в каталог можно писать: он существует и доступен на запись
     * либо его ещё нет, но существующий родитель позволяет его создать.
     * Нужно, чтобы опечатка в пути не приводила к молчаливой потере логов —
     * вместо этого модуль вернётся к каталогу по умолчанию.
     */
    private static function isDirUsable($dir)
    {
        if (is_dir($dir)) {
            return is_writable($dir);
        }

        $parent = dirname($dir);

        while ($parent !== '' && $parent !== '.' && !is_dir($parent)) {
            $upper = dirname($parent);
            if ($upper === $parent) {
                break;
            }
            $parent = $upper;
        }

        return is_dir($parent) && is_writable($parent);
    }

    private static function maskKey($key)
    {
        return strlen($key) > 8
            ? substr($key, 0, 4) . '***' . substr($key, -4)
            : '***';
    }

    private static function isEnabled()
    {
        if (self::$enabled === null) {
            self::$enabled = Option::get('uplab.tilda', 'UPT_LOG_ENABLED', 'N') === 'Y';
        }
        return self::$enabled;
    }

    private static function getMinLevel()
    {
        if (self::$minLevel === null) {
            $level = Option::get('uplab.tilda', 'UPT_LOG_LEVEL', self::LEVEL_ERROR);
            self::$minLevel = array_key_exists($level, self::$priorities) ? $level : self::LEVEL_ERROR;
        }
        return self::$minLevel;
    }

    private static function shouldLog($level)
    {
        if (!self::isEnabled()) {
            return false;
        }
        return (self::$priorities[$level] ?? 0) >= (self::$priorities[self::getMinLevel()] ?? 3);
    }

    /**
     * Возвращает случайную соль имени лог-файла, генерируя её при первом обращении
     * и сохраняя в опции `UPT_LOG_SALT`.
     */
    private static function getSalt()
    {
        if (self::$salt !== null) {
            return self::$salt;
        }

        $salt = Option::get('uplab.tilda', 'UPT_LOG_SALT', '');

        if ($salt === '') {
            $salt = bin2hex(random_bytes(16)); // 32 hex-символа
            try {
                Option::set('uplab.tilda', 'UPT_LOG_SALT', $salt);
            } catch (\Throwable $e) {
                // Игнорируем — соль пересоздастся при следующем запросе
            }
        }

        self::$salt = $salt;
        return $salt;
    }

    /**
     * Возвращает (или создаёт) экземпляр Bitrix FileLogger для текущего дня.
     *
     * При смене даты создаётся новый экземпляр с новым путём к файлу, что
     * обеспечивает суточную ротацию без внешнего cronjob.
     */
    private static function getFileLogger()
    {
        $today = date('Y-m-d');

        if (self::$fileLogger === null || self::$fileLoggerDate !== $today) {
            $logDir = self::getLogDir();

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0750, true);
            }

            // Проверяем защиту и для уже существующего каталога — например,
            // если его создали вручную или он остался от прежней настройки.
            self::protectLogDir($logDir);

            $filename = 'tilda_' . self::getSalt() . '_' . $today . '.log';
            self::$fileLogger     = new FileLogger($logDir . $filename, self::MAX_FILE_SIZE);
            self::$fileLoggerDate = $today;
        }

        return self::$fileLogger;
    }

    private static function write($level, $message, array $context = [])
    {
        if (!self::shouldLog($level)) {
            return;
        }

        // Ключ `body` (ответ Tilda) выносим из JSON-контекста и пишем отдельным
        // блоком целиком, без обрезки: ради чтения такого ответа лог и включают,
        // а внутри JSON-строки экранированный HTML нечитаем.
        $body = null;
        if (array_key_exists('body', $context)) {
            $body = trim((string)$context['body']);
            unset($context['body']);
        }

        // FileLogger пишет сообщение ровно так, как оно пришло: штатный
        // LogFormatter только подставляет плейсхолдеры {date}, {host} и т. п.,
        // но не добавляет ни времени, ни уровня, ни перевода строки. Поэтому
        // формат строки задаём здесь, иначе весь журнал склеится в одну строку.
        $line = date('Y-m-d H:i:s') . ' ' . strtoupper($level) . ' [uplab.tilda] ' . $message;

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($body !== null && $body !== '') {
            $line .= PHP_EOL . self::BODY_START . PHP_EOL . $body . PHP_EOL . self::BODY_END;
        }

        // Плейсхолдеры отдаём равными самим себе: иначе LogFormatter заменил бы
        // {date}/{host}/{delimiter}, случайно встреченные в HTML страницы Tilda.
        self::getFileLogger()->{$level}(
            $line . PHP_EOL,
            ['date' => '{date}', 'host' => '{host}', 'delimiter' => '{delimiter}']
        );
    }
}
