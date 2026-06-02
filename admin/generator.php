<?php

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

IncludeModuleLangFile(__FILE__);

global $APPLICATION;

$APPLICATION->SetTitle(GetMessage("NEW_PAGE_FROM_TILDA_TITLE"));

CJSCore::Init("jquery");
Bitrix\Main\Loader::includeModule("uplab.tilda");
$fullPages = CUplabTilda::get_full_pages(array(), false);
$is1251 = strripos(LANG_CHARSET, "1251");

if ($_REQUEST["data-send"] == "Y") {
    return require __DIR__ . "/create-dir.php";
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
?>

    <form id="create-page-form" action="">

        <input type="hidden" value="<?= bitrix_sessid() ?>" name="send_sessid"/>
        <input type="hidden" name="data-send" value="Y">

        <div id="module-container">

            <?php
            $upTildaTabControl = new CAdminTabControl(
                "upTildaTabControl",
                array(
                    array(
                        "DIV"   => "tab1",
                        "TAB"   => GetMessage("CREATE_PAGE"),
                        "TITLE" => GetMessage("NEW_TILDA_PAGE")
                    ),
                )
            );
            $upTildaTabControl->Begin();
            $upTildaTabControl->BeginNextTab();
            ?>


            <style>
                [data-infomsg-tal] .adm-info-message {
                    text-align: left;
                }

                [data-infomsg-w100p] .adm-info-message {
                    width: 100%;
                    display: block;
                    box-sizing: border-box;
                }

                [data-infomsg-db] .adm-info-message {
                    display: block !important;
                }

                .up-tilda-select,
                .up-tilda-input {
                    width: 400px;
                }
            </style>


            <tr class="heading">
                <td colspan="2"><?= GetMessage("PAGE_IN_TILDA") ?></td>
            </tr>


            <tr>
                <td width="50%">
                    <?= GetMessage("CHOOSE_PROJECT") ?>
                </td>
                <td width="50%">
                    <select class="up-tilda-select" name="project_id">
                        <?php
                        foreach ($fullPages as $id => $project) { ?>
                            <option value="<?= $id ?>"><?= $project["title"] ?></option>
                            <?php
                        } ?>
                    </select>
                </td>
            </tr>


            <tr>
                <td width="50%">
                    <?= GetMessage("CHOOSE_PAGE") ?>
                </td>
                <td width="50%">

                    <?php
                    $f = 1;
                    foreach ($fullPages as $id => $project): ?>

                        <select name="project_page[<?= $id ?>]" class="up-tilda-select js-project-page"
                                style="<?= $f ? "" : "display:none;" ?>">
                            <?php
                            foreach ($project["pages"] as $page): ?>
                                <?php
                                $title = $page["title"];
                                if ($is1251 !== false) {
                                    $title = $APPLICATION->ConvertCharset($title, "UTF-8", "CP1251");
                                }
                                ?>
                                <option value="<?= $page["id"] ?>"><?= $title ?></option>
                            <?php
                            endforeach; ?>
                        </select>

                        <?php
                        $f = 0; endforeach; ?>

                </td>
            </tr>

            <tr class="heading">
                <td colspan="2"><?= GetMessage("PAGE_ON_WEBSITE") ?></td>
            </tr>

            <tr>
                <td width="50%">
                    <?= GetMessage("PATH_FROM_ROOT") ?>
                </td>
                <td width="50%">
                    <input type="text" class="up-tilda-input" maxlength="255" value="" name="page_path">
                </td>
            </tr>

            <tr>
                <td width="50%">
                    <?= GetMessage("SECTION_TITLE") ?>
                </td>
                <td width="50%">
                    <input type="text" class="up-tilda-input" maxlength="255" value="" name="section_title">
                </td>
            </tr>

            <tr>
                <td width="50%">
                    <?= GetMessage("PAGE_TITLE") ?>
                </td>
                <td width="50%">
                    <input type="text" class="up-tilda-input" maxlength="255" value="" name="page_title">
                </td>
            </tr>

            <tr>
                <td width="50%">
                    <?= GetMessage("PAGE_DESCRIPTION") ?>
                </td>
                <td width="50%">
                    <textarea type="text" class="up-tilda-input" maxlength="255" value="" name="page_descr"></textarea>
                </td>
            </tr>

            <tr>
                <td colspan="2" align="center" id="up-tilda-result">

                </td>
            </tr>

            <?php
            $upTildaTabControl->Buttons(); ?>
            <input type="submit" value="<?= GetMessage("CREATE_BTN") ?>" class="adm-btn-green"/>
            <?php
            $upTildaTabControl->End(); ?>
            <?= BeginNote('align="center" data-infomsg-db data-infomsg-tal'); ?>
            <ul>
                <li>
                    <?= GetMessage("DIR_NAME_WARNING_STEP1") ?>
                </li>
                <li>
                    <?= GetMessage("REWRITE_WARNING_STEP2") ?>
                </li>
            </ul>
            <?= EndNote(); ?>
        </div>
    </form>

    <script>
        jQuery(function ($) {
            $("select[name=project_id]").on("change", function () {
                var $this = $(this);
                var project = $this.val();

                $(".js-project-page")
                    .hide()
                    .filter('[name="project_page[' + project + ']"]')
                    .show();

            });

            var $result = $('#up-tilda-result');

            $('#create-page-form').on('submit', function (e) {
                e.preventDefault();
                var $this = $(this);
                var url = location.pathname;
                $.post(url, $this.serialize(), function (res) {
                    // console.log(res);
                    clearTimeout(window.createPageMsg);
                    $result.html(res);
                    window.createPageMsg = setTimeout(function () {
                        $result.html('');
                    }, 5000);
                });
            });

            $result.hover(function () {
                clearTimeout(window.createPageMsg)
            });
        });
    </script>
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");