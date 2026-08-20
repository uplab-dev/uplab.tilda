<?php
$MESS['uplab.tilda_ERROR_CURL']     = 'нет ответа от Tilda (curl): ';
$MESS['uplab.tilda_ERROR_FGC']      = 'нет ответа от Tilda (file_get_contents)';
$MESS['uplab.tilda_ERROR_PROTOCOL'] = 'адрес API должен начинаться с https://, проверьте настройки модуля';

$MESS['uplab.tilda_LOG_API_URL_REJECTED']      = 'Адрес API отклонён, используется адрес по умолчанию';
$MESS['uplab.tilda_LOG_NON_HTTPS_BLOCKED']     = 'Запрос не по HTTPS заблокирован';
$MESS['uplab.tilda_LOG_CURL_FAILED']           = 'Запрос к Tilda не выполнен (cURL)';
$MESS['uplab.tilda_LOG_FGC_FAILED']            = 'Запрос к Tilda не выполнен (file_get_contents)';
$MESS['uplab.tilda_LOG_REDIRECT_NOT_FOLLOWED'] = 'Редирект не выполнен: запасной транспорт разрешает только HTTPS';
$MESS['uplab.tilda_LOG_CHECK_CONN_INVALID']    = 'Некорректный ответ API при проверке подключения';

$MESS['uplab.tilda_ERROR_STALLED']  = 'передача оборвалась: за #SECONDS# с не пришло ни одного нового байта';
$MESS['uplab.tilda_ERROR_ATTEMPTS'] = '(попыток: #COUNT#)';
$MESS['uplab.tilda_LOG_RETRY']      = 'Запрос к Tilda не выполнен, повторяем';
$MESS['uplab.tilda_LOG_STALLED']    = 'Передача от Tilda зависла, запрос прерван';
