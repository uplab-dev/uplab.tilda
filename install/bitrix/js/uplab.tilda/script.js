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

var uTildaPost = function(params, callback){
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "/bitrix/tools/uplab.tilda_post.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function(){
        if(xhr.readyState !== 4 || !callback){
            return;
        }

        // Ответ — JSON вида {status: 'success'|'error', message: '...'}.
        var response;
        try {
            response = JSON.parse(xhr.responseText);
        } catch (e) {
            response = {status: 'error', message: xhr.responseText};
        }

        callback(response);
    };

    var pairs = [];
    for(var key in params){
        if(params.hasOwnProperty(key)){
            pairs.push(encodeURIComponent(key) + "=" + encodeURIComponent(params[key]));
        }
    }
    xhr.send(pairs.join("&"));
};

// Показывает уведомление диалогом BX.UI.Dialogs.MessageBox.alert; при отсутствии модуля ui — откат на alert().
var uTildaNotify = function(message){
    if(!message){
        return;
    }

    var win = window.top || window;

    if(win.BX && win.BX.UI && win.BX.UI.Dialogs && win.BX.UI.Dialogs.MessageBox){
        win.BX.UI.Dialogs.MessageBox.alert(win.BX.util.htmlspecialchars(message));
    } else {
        alert(message);
    }
};

// Запрашивает подтверждение через BX.UI.Dialogs.MessageBox; при отсутствии модуля ui — откат на confirm().
var uTildaConfirm = function(message, onConfirm){
    message = message || '';

    var win = window.top || window;

    if(win.BX && win.BX.UI && win.BX.UI.Dialogs && win.BX.UI.Dialogs.MessageBox){
        win.BX.UI.Dialogs.MessageBox.confirm(
            win.BX.util.htmlspecialchars(message),
            function(messageBox){
                messageBox.close();
                onConfirm();
            },
            function(messageBox){
                messageBox.close();
            }
        );
    } else if(confirm(message)) {
        onConfirm();
    }
};

// Флаг «запрос в процессе» — блокирует повторный запуск, пока ждём ответ сервера.
var uTildaBusy = false;

// Гасит/возвращает пункты меню Tilda (ссылки, вызывающие uTildaClearCache*),
// чтобы их нельзя было кликнуть, пока выполняется запрос.
var uTildaSetMenuBlocked = function(blocked){
    var win = window.top || window;
    var links = win.document.querySelectorAll('a[href*="uTildaClearCache"]');
    for(var i = 0; i < links.length; i++){
        links[i].style.pointerEvents = blocked ? 'none' : '';
        links[i].style.opacity = blocked ? '0.5' : '';
    }
};

// Отправляет действие на сервер с индикатором ожидания (BX.showWait/closeWait)
// и блокировкой пунктов меню от повторного клика, пока запрос не завершится.
var uTildaRun = function(params){
    if(uTildaBusy){
        return;
    }
    uTildaBusy = true;

    var win = window.top || window;

    uTildaSetMenuBlocked(true);
    if(win.BX && win.BX.showWait){
        win.BX.showWait();
    }

    uTildaPost(params, function(response){
        if(win.BX && win.BX.closeWait){
            win.BX.closeWait();
        }
        uTildaSetMenuBlocked(false);
        uTildaBusy = false;
        uTildaNotify(response.message);
    });
};

var uTildaClearCache = function(confirmMsg){
    uTildaConfirm(confirmMsg, function(){
        uTildaRun({clearCache: "Y", sessid: BX.bitrix_sessid()});
    });
};

var uTildaClearCacheList = function(confirmMsg){
    uTildaConfirm(confirmMsg, function(){
        uTildaRun({clearCacheList: "Y", sessid: BX.bitrix_sessid()});
    });
};

// Проверяет подключение к Tilda API с текущими значениями полей публичного и секретного ключей.
// checkingMsg — текст кнопки на время запроса, emptyKeysMsg — сообщение о незаполненных ключах.
var uTildaCheckConnection = function(checkingMsg, emptyKeysMsg){
    var btn = document.getElementById('uTildaCheckConnectionBtn');
    if (!btn || btn.disabled) {
        return;
    }

    var publicKeyEl = document.querySelector('[name="UPT_PUBLIC_KEY"]');
    var secretKeyEl = document.querySelector('[name="UPT_SECRET_KEY"]');

    // Пустые ключи отсекаем на клиенте, чтобы не гонять заведомо ошибочный запрос.
    if (!publicKeyEl || !secretKeyEl || !publicKeyEl.value.trim() || !secretKeyEl.value.trim()) {
        uTildaNotify(emptyKeysMsg);
        return;
    }

    btn.disabled = true;
    var origText = btn.textContent !== undefined ? btn.textContent : btn.innerText;
    if (checkingMsg) {
        if (btn.textContent !== undefined) {
            btn.textContent = checkingMsg;
        } else {
            btn.innerText = checkingMsg;
        }
    }

    uTildaPost({
        checkConnection: 'Y',
        sessid: BX.bitrix_sessid(),
        publicKey: publicKeyEl.value.trim(),
        secretKey: secretKeyEl.value.trim()
    }, function(response){
        btn.disabled = false;
        if (btn.textContent !== undefined) {
            btn.textContent = origText;
        } else {
            btn.innerText = origText;
        }
        uTildaNotify(response.message);
    });
};

// Запрашивает подтверждение и удаляет все файлы логов через AJAX.
var uTildaClearLogs = function(confirmMsg){
    uTildaConfirm(confirmMsg, function(){
        uTildaRun({clearLogs: 'Y', sessid: BX.bitrix_sessid()});
    });
};