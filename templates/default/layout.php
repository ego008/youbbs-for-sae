<?php
if (!defined('IN_SAESPOT')) exit('error: 403 Access Denied');
ob_start();

echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="zh-CN" lang="zh-CN">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<title>',$title,'</title>
<meta content="True" name="HandheldFriendly" />
<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
<link href="/static/default/style.css" rel="stylesheet" type="text/css" />
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
<link href="/feed" rel="alternate" title="',htmlspecialchars($options['name']),' - ATOM Feed" type="application/atom+xml"/>
<script src="',$options['jquery_lib'],'" type="text/javascript"></script>
<link rel="top" title="Back to Top" href="#" />
';

if( (isset($t_obj) && $t_obj['cid']==1 ) || (isset($c_obj) && $c_obj['id']==1 )){
    echo '<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW" />',"\n";
}

if($options['head_meta']){
    echo $options['head_meta'];
}

if(isset($meta_kw) && $meta_kw){
    echo '
<meta name="keywords" content="',$meta_kw,'" />';
}
if(isset($meta_des) && $meta_des){
    echo '
<meta name="description" content="',$meta_des,'" />';
}
if(isset($canonical)){
    echo '
<link rel="canonical" href="http://',$_SERVER['HTTP_HOST'],$canonical,'" />';
}

echo '
<script type="text/javascript">
    $(function(){
        $("#go-to-top").click(function(){
            $("html, body").animate({"scrollTop": 0}, 400);
            return false;
        });
        $(window).scroll(function() {
            var top = $(document).scrollTop();
            var g = $("#go-to-top");
            if (top > 300 && g.is(":hidden")) {
                g.fadeIn();
            } else if(top < 300 && g.is(":visible")) {
                g.fadeOut();
            }
        });
    })

</script>

</head>
<body>
<div class="header-wrap">
    <div class="header">
        <div class="logo"><a href="/" name="top">',htmlspecialchars($options['name']),'</a></div>';
if($is_spider){
    if($bot_nodes){
        foreach(array_slice($bot_nodes, 0, intval($options['hot_node_num']), true) as $k=>$v ){
            echo '<a href="/',$k,'">',$v,'</a> ';
        }
    }else if($newest_nodes){
        foreach( $newest_nodes as $k=>$v ){
            echo '<a href="/',$k,'">',$v,'</a> ';
        }
    }
}else{
echo '
        <div class="scbox">

<script type="text/javascript">
    var dispatch = function() {
        q = document.getElementById("q");
        if (q.value != "" && q.value != "站内搜索") {
            window.open(\'http://www.google.de/search?q=site:',$_SERVER['HTTP_HOST'],'%20\' + q.value, "_blank");
            return false;
        } else {
            return false;
        }
    }
</script>

        <form role="search" method="get" id="searchform" onsubmit="return dispatch()" target="_blank">
            <input type="text" maxlength="30" onfocus="if(this.value==\'站内搜索\') this.value=\'\';" onblur="if(this.value==\'\') this.value=\'站内搜索\';" value="站内搜索" name="q" id="q">
        </form>
        </div>';
}
echo '
        <div class="banner">';

if($cur_user){
    echo '<img src="',$options['base_avatar_url'],'/',$cur_user['avatar'],'.jpg!mini" alt="',$cur_user['name'],'"/>&nbsp;&nbsp;&nbsp;';

    if(!$cur_user['password']){
        //echo '<a href="/setting#3" style="color:yellow;">设置登录密码</a>&nbsp;&nbsp;&nbsp;';
    }

    if($cur_user['notic']){
        $notic_n = count(array_unique(explode(',', $cur_user['notic'])))-1;
        echo '<a href="/notifications" style="color:yellow;">',$notic_n,'条提醒</a>&nbsp;&nbsp;&nbsp;';
    }
    if($cur_user['flag'] == 0){
        echo '<span style="color:yellow;">已被禁用</span>&nbsp;&nbsp;&nbsp;';
    }else if($cur_user['flag'] == 1){
        echo '<span style="color:yellow;">在等待审核</span>&nbsp;&nbsp;&nbsp;';
    }
    echo '<a href="/favorites" title="收藏的帖子">★</a>&nbsp;&nbsp;&nbsp;<a href="/member/',$cur_user['id'],'">',$cur_user['name'],'</a>&nbsp;&nbsp;&nbsp;<a href="/setting">设置</a>&nbsp;&nbsp;&nbsp;<a href="/logout">退出</a>';
}else{
    if($options['wb_key'] && $options['wb_secret']){
        echo '<a href="/wblogin" rel="nofollow"><img src="/static/weibo_login_55_24.png" alt="微博登录" title="用微博帐号登录"/></a>&nbsp;&nbsp;';
    }
    if($options['qq_appid'] && $options['qq_appkey']){
        echo '<a href="/qqlogin" rel="nofollow"><img src="/static/qq_logo_55_24.png" alt="QQ登录" title="用QQ登录"/></a>&nbsp;&nbsp;';

    }
    echo '<a href="/login" rel="nofollow">登录</a>';
    if(!($options['wb_key'] && $options['wb_secret']) && !($options['qq_appid'] && $options['qq_appkey'])){
        if(!$options['close_register']){
            echo '&nbsp;&nbsp;<a href="/sigin">注册</a>';
        }
    }
}
echo '       </div>
        <div class="c"></div>
    </div>
    <!-- header end -->
</div>

<div class="main-wrap">
    <div class="main">
        <div class="main-content">';

include($pagefile);

echo '       </div>
        <!-- main-content end -->
        <div class="main-sider">';

include(ROOT . '/templates/default/sider.php');
echo '       </div>
        <!-- main-sider end -->
        <div class="c"></div>
    </div>
    <!-- main end -->
    <div class="c"></div>
</div>';

echo '
<div class="footer-wrap">
    <div class="footer">
    <p>&copy; Copyright <a href="/">',$options['name'],'</a> • <a rel="nofollow" href="/feed">Atom Feed</a>';
if($options['icp']){
    echo ' • <a href="http://www.miibeian.gov.cn/" target="_blank" rel="nofollow">',$options['icp'],'</a>';
}
if($is_mobie){
    echo ' • <a href="/viewat-mobile">手机模式</a>';
}

echo '    </p>
    <p>Powered by <a href="http://youbbs.sinaapp.com" target="_blank">YouBBS v',SAESPOT_VER,'</a></p>';
if($options['show_debug']){
    $mtime = explode(' ', microtime());
    $totaltime = number_format(($mtime[1] + $mtime[0] - $starttime), 6);
    $querycount = $DBS->querycount;
    if(isset($DBM)) $querycount += $DBM->querycount;
    echo '<p>Processed in ',$totaltime,' second(s), ',$querycount,' queries</p>';
}
echo '    </div>
<a style="display: none; " rel="nofollow" href="#top" id="go-to-top">▲</a>
    <!-- footer end -->
</div>

<script src="/static/js/jquery.lazyload.min.js" type="text/javascript" charset="utf-8"></script>

<script type="text/javascript" charset="utf-8">
$(function() {
    $(".main-box img").lazyload({
        //placeholder : "/static/grey.gif",
        //effect : "fadeIn"
    });
});
</script>
';
if($options['analytics_code']){
    echo $options['analytics_code'];
}

echo '
</body>
</html>';

$_output = ob_get_contents();
ob_end_clean();

// 304
if(!$options['show_debug']){
    $etag = md5($_output);
    if($_SERVER['HTTP_IF_NONE_MATCH'] == $etag){
        header("HTTP/1.1 304 Not Modified");
        header("Status: 304 Not Modified");
        header("Etag: ".$etag);
        exit;
    }else{
        header("Etag: ".$etag);
    }
}

echo $_output;

?>