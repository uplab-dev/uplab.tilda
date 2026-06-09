BX.addCustomEvent('OnEditorInitedBefore', function (editor) {
    // Код суррогата (узла) визуального редактора для тега Tilda.
    var TAG_CODE = 'uplabtilda';

    // Находит весь тег: [UPLABTILDA PROJECT=.. PAGE=.. HIDEPAGETEMPLATE=.. MOVERESOURCESTO=..]
    var TAG_REGEX = /\[UPLABTILDA\s+[^\]]*\]/gi;

    // Адрес попапа выбора/редактирования страницы Tilda.
    var POPUP_URL = '/bitrix/admin/uplab.tilda_editor_popup.php';

    // Суррогат, который сейчас редактируется (null — когда вставляем новый тег).
    var currentEditTag = null;

    // Разбирает строку тега в объект атрибутов: {PROJECT, PAGE, HIDEPAGETEMPLATE, MOVERESOURCESTO}.
    function parseTagAttributes(tagStr) {
        var attrs = {};
        var re = /([A-Z]+)=([^\s\]]+)/gi;
        var m;
        while ((m = re.exec(tagStr)) !== null) {
            attrs[m[1].toUpperCase()] = m[2];
        }
        return attrs;
    }

    // Достаёт атрибуты из объекта суррогата (сохранённые при парсинге либо из исходного html).
    function getTagAttributes(tag) {
        if (tag && tag.params && tag.params.attributes) {
            return tag.params.attributes;
        }
        if (tag && tag.params && tag.params.html) {
            return parseTagAttributes(tag.params.html);
        }
        return {};
    }

    // Человекочитаемый заголовок суррогата.
    function buildSurrogateTitle(attrs) {
        var title = 'Tilda';
        if (attrs.PROJECT) {
            title += ': ' + BX.message('uplab.tilda_SURROGATE_PROJECT') + ' ' + attrs.PROJECT;
        }
        if (attrs.PAGE) {
            title += ' / ' + BX.message('uplab.tilda_SURROGATE_PAGE') + ' ' + attrs.PAGE;
        }
        if (attrs.HIDEPAGETEMPLATE) {
            title += ' (' + BX.message('uplab.tilda_SURROGATE_NO_TEMPLATE') + ')';
        }
        if (attrs.MOVERESOURCESTO) {
            title += ' [' + attrs.MOVERESOURCESTO + ']';
        }
        return title;
    }

    // Открывает попап. attrs/tag заданы — режим редактирования (форма предзаполняется).
    function openDialog(attrs, tag) {
        currentEditTag = tag || null;

        // CSRF-токен передаём в URL; на сабмите форма проверяется через check_bitrix_sessid().
        var params = ['sessid=' + encodeURIComponent(BX.bitrix_sessid())];
        if (attrs) {
            if (attrs.PROJECT) {
                params.push('EDIT_PROJECT=' + encodeURIComponent(attrs.PROJECT));
            }
            if (attrs.PAGE) {
                params.push('EDIT_PAGE=' + encodeURIComponent(attrs.PAGE));
            }
            if (attrs.HIDEPAGETEMPLATE) {
                params.push('EDIT_HIDEPAGETEMPLATE=' + encodeURIComponent(attrs.HIDEPAGETEMPLATE));
            }
            if (attrs.MOVERESOURCESTO) {
                params.push('EDIT_MOVERESOURCESTO=' + encodeURIComponent(attrs.MOVERESOURCESTO));
            }
        }

        var url = POPUP_URL + '?' + params.join('&');

        var codeDialog = new BX.CDialog({
            title: BX.message('uplab.tilda_MODULE_NAME'),
            content_url: url,
            min_width: 800,
            min_height: 500,
            width: 800,
            height: 500
        });

        codeDialog.Show();
    }

    // Вызывается из попапа при сохранении. Вставляет новый тег либо обновляет редактируемый суррогат.
    window.tildaTag = function (tag) {
        if (currentEditTag) {
            currentEditTag.params.html = tag;
            currentEditTag.params.attributes = parseTagAttributes(tag);
            currentEditTag = null;
        } else {
            editor.InsertHtml(tag, editor.selection.GetRange());
        }

        setTimeout(function () {
            editor.synchro.FullSyncFromIframe();
        }, 50);
    };

    // Кнопка на тулбаре — вставка нового тега.
    this.AddButton({
        compact: true,
        iconClassName: 'up_tilda_editor_button',
        id: 'up_tilda_editor_button',
        src: '/bitrix/images/uplab.tilda/uplab.tilda.png',
        name: BX.message('uplab.tilda_MODULE_NAME'),
        toolbarSort: 200,
        handler: function (ev) {
            openDialog();
        }
    });

    // Парсер: превращает текст [UPLABTILDA ...] в кликабельный суррогат редактора.
    this.AddCustomParser(function (content) {
        return content.replace(TAG_REGEX, function (fullTag) {
            var attrs = parseTagAttributes(fullTag);
            var title = buildSurrogateTitle(attrs);
            var surrogate = editor.phpParser.GetSurrogateHTML(
                TAG_CODE,
                title,
                title,
                { html: fullTag, attributes: attrs }
            );

            return surrogate || fullTag;
        });
    });

    // Сериализация суррогата обратно в исходный тег при сохранении содержимого.
    BX.addCustomEvent('OnGetBxNodeList', function () {
        var phpParser = this.phpParser;
        phpParser.arBxNodes[TAG_CODE] = {
            Parse: function (params) {
                return phpParser._GetUnParsedContent(params.html);
            }
        };
    });

    // Двойной клик по суррогату — открыть форму редактирования с текущими значениями.
    BX.addCustomEvent('OnSurrogateDblClick', function (e, tag) {
        if (!tag || tag.tag !== TAG_CODE) {
            return;
        }
        if (typeof editor.bxTags[tag.id] === 'undefined') {
            return;
        }

        openDialog(getTagAttributes(tag), tag);
    });
});