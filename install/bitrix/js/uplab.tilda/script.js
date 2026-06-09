var uTildaPost = function(params, callback){
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "/bitrix/tools/uplab.tilda_post.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function(){
        if(xhr.readyState === 4 && callback){
            callback(xhr.responseText);
        }
    };

    var pairs = [];
    for(var key in params){
        if(params.hasOwnProperty(key)){
            pairs.push(encodeURIComponent(key) + "=" + encodeURIComponent(params[key]));
        }
    }
    xhr.send(pairs.join("&"));
};
var uTildaClearCache = function(confirmMsg){
    confirmMsg = confirmMsg || '';
    if(confirm(confirmMsg)) {
        uTildaPost(
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
        uTildaPost(
            {clearCacheList: "Y", sessid: BX.bitrix_sessid()},
            function( data ) {
                alert(data);
            }
        );
    }
};