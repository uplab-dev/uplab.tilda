<?php

if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/uplab.tilda/admin/cache_table.php")) {
    require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/uplab.tilda/admin/cache_table.php");
} else {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/uplab.tilda/admin/cache_table.php");
}