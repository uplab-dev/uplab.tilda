<?php

if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/uplab.tilda/admin/editor_popup.php")) {
    require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/uplab.tilda/admin/editor_popup.php");
} else {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/uplab.tilda/admin/editor_popup.php");
}