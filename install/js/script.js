var uTildaClearCache = function(confirmMsg){
    confirmMsg = confirmMsg || '';
    if(confirm(confirmMsg)) {
        $.post(
            "/bitrix/tools/uplab.tilda_post.php",
            {clearCache: "Y", sessid: BX.bitrix_sessid()},
            function( data ) {
                alert(data);
            }
        );
    }
};

var uTildaClearCacheList = function(confirmMsg){
    confirmMsg = confirmMsg || '';
    if(confirm(confirmMsg)) {
        $.post(
            "/bitrix/tools/uplab.tilda_post.php",
            {clearCacheList: "Y", sessid: BX.bitrix_sessid()},
            function( data ) {
                alert(data);
            }
        );
    }
};