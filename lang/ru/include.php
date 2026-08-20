<?php
/**
 * Общий файл языковых констант
 */

$MESS['uplab.tilda_SETTINGS_TAB_NAME'] = 'Настройки доступа к API';
$MESS['uplab.tilda_PUBLIC_KEY'] = 'Публичный ключ:';
$MESS['uplab.tilda_SECRET_KEY'] = 'Секретный ключ:';
$MESS['uplab.tilda_SETTINGS_WARNING'] = "<b>Взаимодействие с API доступно только для тарифа Business.</b><br />Для начала работы, Вам нужно получить Публичный и Секретный ключи в личном кабинете Tilda (Настройки сайта - Экспорт - API Integration).";

$MESS['uplab.tilda_MENU_TITLE'] = 'Интеграция Tilda';
$MESS['uplab.tilda_CLEAR_CACHE_MENU_TITLE'] = 'Полностью очисить кэш';
$MESS['uplab.tilda_CLEAR_CACHE_LIST_MENU_TITLE'] = 'Обновить список страниц';
$MESS['uplab.tilda_CLEAR_CACHE_CONFIRM'] = 'Удалить кэш?';
$MESS['uplab.tilda_CLEAR_CACHE_LIST_CONFIRM'] = 'Обновить список страниц?';
$MESS['uplab.tilda_ADD_NEW_PAGE_MENU_TITLE'] = 'Добавить страницу';

$MESS['uplab.tilda_CACHE_CLEARED'] = 'Кеш успешно удалён';
$MESS['uplab.tilda_CACHE_LIST_CLEARED'] = 'Список страниц успешно обновлен';

$MESS['uplab.tilda_PAGE_SELECT'] = 'Выбор страницы';
$MESS['uplab.tilda_SELECT_PROJECT'] = 'Выберите проект:';
$MESS['uplab.tilda_SELECT_PAGE'] = 'Выберите страницу:';
$MESS['uplab.tilda_NO_TEMPLATE'] = 'Не выводить шаблон сайта:';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS'] = 'Перемещать стили и скрипты Tilda:';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS_NONE'] = 'не перемещать';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS_HEADEND'] = 'в конец области head';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS_BODYEND'] = 'в конец области body';

$MESS['uplab.tilda_NO_PROJECTS'] = 'Нет ни одного проекта (страницы) или не указаны API ключи Тильда.';
$MESS['uplab.tilda_NO_KEYS'] = 'Указать ключи';

$MESS['uplab.tilda_PAGES_LOADING'] = 'Загрузка списка страниц...';
$MESS['uplab.tilda_PAGES_EMPTY'] = 'В проекте нет страниц.';
$MESS['uplab.tilda_PAGES_LOAD_ERROR'] = 'Не удалось загрузить список страниц.';
$MESS['uplab.tilda_PAGES_LOAD_TIMEOUT'] = 'Tilda не ответила за отведённое время. Список страниц не загружен.';
$MESS['uplab.tilda_PROJECTS_LOAD_ERROR'] = 'Не удалось загрузить список проектов Tilda: #MESSAGE#';
$MESS['uplab.tilda_RETRY'] = 'Повторить';
$MESS['uplab.tilda_PAGE_NOT_SELECTED'] = 'Выберите страницу.';

$MESS['uplab.tilda_NO_MODULE'] = 'Модуль «Интеграция с Tilda» (uplab.tilda) не подключён';

$MESS['uplab.tilda_PAGES'] = 'Страницы';

$MESS['uplab.tilda_BASE_SETTINGS'] = 'Настройки соединения с Tilda';
$MESS['uplab.tilda_API_URL'] = 'Базовый URL Tilda API (по умолчанию https://api.tildacdn.info/v1/).';
$MESS['uplab.tilda_API_URL_INVALID'] = 'Модуль «Интеграция с Tilda» (uplab.tilda): адрес API в настройках некорректен, используется #URL#';
$MESS['uplab.tilda_TIMEOUT'] = 'Общий предел времени запроса со всеми повторными попытками, секунд.';
$MESS['uplab.tilda_CONNECTTIMEOUT'] = 'Количество секунд ожидания при попытке соединения. Используйте 0 для бесконечного ожидания.';
$MESS['uplab.tilda_LIST_CACHE_TTL'] = 'Срок кэширования списков проектов и страниц, секунд. Определяет, через сколько новая страница Tilda появится в редакторе (контент страниц кэшируется отдельно, на неделю).';

$MESS['uplab.tilda_SESSION_EXPIRED'] = 'Ваша сессия истекла! Пожалуйста, перегрузите страницу.';
$MESS['uplab.tilda_UNKNOWN_ACTION'] = 'Неизвестное действие.';

$MESS['uplab.tilda_LOGGING_TAB_NAME']   = 'Логирование';
$MESS['uplab.tilda_LOGGING_SECTION']    = 'Настройки логирования';
$MESS['uplab.tilda_LOG_ENABLED']        = 'Включить логирование:';
$MESS['uplab.tilda_LOG_LEVEL']          = 'Минимальный уровень записи:';
$MESS['uplab.tilda_LOG_LEVEL_DEBUG']    = 'Debug — все события (запросы, кэш, ответы)';
$MESS['uplab.tilda_LOG_LEVEL_INFO']     = 'Info — сохранение в кэш и выше';
$MESS['uplab.tilda_LOG_LEVEL_WARNING']  = 'Warning — предупреждения и ошибки';
$MESS['uplab.tilda_LOG_LEVEL_ERROR']    = 'Error — только ошибки';
$MESS['uplab.tilda_LOG_DIR'] = 'Каталог для логов. Оставьте пустым — логи пойдут в каталог модуля. Путь без слэша в начале (upload/tilda_logs) считается от корня сайта, со слэшем (/var/log/tilda) — абсолютным путём на сервере:';
$MESS['uplab.tilda_LOG_DIR_LABEL']      = 'Лог-файлы записываются в:';
$MESS['uplab.tilda_LOG_DIR_EXISTS']     = 'каталог существует';
$MESS['uplab.tilda_LOG_DIR_NOT_EXISTS'] = 'каталог будет создан автоматически при первой записи';
$MESS['uplab.tilda_LOG_DIR_FOREIGN']    = 'Модуль не создавал эти файлы в каталоге логов: #FILES#';
$MESS['uplab.tilda_LOG_DIR_LEGACY']     = 'Журналы прежнего формата (до версии 3.3.2, по файлу на каждый день): #COUNT# шт., #SIZE#. Модуль их не удаляет — они могут понадобиться для разбора инцидента. Удалить их можно кнопкой «Очистить логи».';

$MESS['uplab.tilda_CHECK_CONNECTION']          = 'Проверить подключение';
$MESS['uplab.tilda_CHECK_CONN_CHECKING']       = 'Проверка...';
$MESS['uplab.tilda_CHECK_CONN_EMPTY_KEYS']     = 'Укажите публичный и секретный ключи.';
$MESS['uplab.tilda_CHECK_CONN_SUCCESS']        = 'Подключение успешно. Найдено проектов: #COUNT#.';
$MESS['uplab.tilda_CHECK_CONN_API_ERROR']      = 'Ошибка Tilda API';
$MESS['uplab.tilda_CHECK_CONN_CURL_ERROR']     = 'Ошибка cURL: ';
$MESS['uplab.tilda_CHECK_CONN_INVALID_RESP']   = 'Некорректный ответ от Tilda API.';

$MESS['uplab.tilda_CLEAR_LOGS']         = 'Удалить файлы логов:';
$MESS['uplab.tilda_CLEAR_LOGS_BTN']     = 'Очистить логи';
$MESS['uplab.tilda_CLEAR_LOGS_CONFIRM'] = 'Удалить все файлы логов? Действие необратимо.';
$MESS['uplab.tilda_LOGS_CLEARED']         = 'Логи очищены (#COUNT# файл(ов)).';
$MESS['uplab.tilda_LOGS_CLEARED_FOREIGN'] = 'В каталоге остались файлы, которые модуль не создавал, — они не удалены: #FILES#';

$MESS['uplab.tilda_RESILIENCE_SECTION'] = 'Поведение при сбоях Tilda';
$MESS['uplab.tilda_ATTEMPTS'] = 'Число попыток запроса (1–5). У Tilda встречается обрыв отдачи тяжёлых страниц, при котором следующая попытка проходит целиком:';
$MESS['uplab.tilda_STALL_TIMEOUT'] = 'Считать передачу зависшей, если новых байт нет столько секунд (0 — не проверять). Проверяется только начавшаяся передача, ожидание первого байта не прерывается:';
$MESS['uplab.tilda_STALE_ON_ERROR'] = 'При сбое Tilda отдавать устаревшую копию страницы:';
$MESS['uplab.tilda_STALE_NOTE'] = 'Без этой опции блок Tilda при транспортном сбое исчезает со страницы. С включённой опцией отдаётся последняя успешно загруженная версия (копия хранится 30 дней), в админке появляется красная плашка, в журнал событий пишется предупреждение, а на самой странице сотрудники видят пометку об устаревшей версии — посетителям она не показывается. Содержательный ответ API (ошибка, страница не найдена или пуста) копией не подменяется: страница могла быть снята с публикации. Копия удаляется при очистке кэша страницы и при полной очистке кэша.';
