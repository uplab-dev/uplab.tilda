<?php

if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/uplab.tilda/tools/post.php")) {
    require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/uplab.tilda/tools/post.php");
} else {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/uplab.tilda/tools/post.php");
}