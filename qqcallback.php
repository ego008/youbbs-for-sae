<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

error_reporting(0);
session_start();

$_SESSION["appid"]    = $options['qq_appid'];
$_SESSION["appkey"]   = $options['qq_appkey'];

$_SESSION["callback"] = 'http://'.$_SERVER['HTTP_HOST'].'/qqcallback';

include(ROOT . "/api/qq_utils.php");

function qq_callback()
{
    //debug
    //print_r($_REQUEST);
    //print_r($_SESSION);

    if($_REQUEST['state'] == $_SESSION['state']) //csrf
    {
        $token_url = "https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&"
            . "client_id=" . $_SESSION["appid"]. "&redirect_uri=" . urlencode($_SESSION["callback"])
            . "&client_secret=" . $_SESSION["appkey"]. "&code=" . $_REQUEST["code"];

        $response = get_url_contents($token_url);
        if (strpos($response, "callback") !== false)
        {
            $lpos = strpos($response, "(");
            $rpos = strrpos($response, ")");
            $response  = substr($response, $lpos + 1, $rpos - $lpos -1);
            $msg = json_decode($response);
            if (isset($msg->error))
            {
                echo "<h3>error:</h3>" . $msg->error;
                echo "<h3>msg  :</h3>" . $msg->error_description;
                exit;
            }
        }

        $params = array();
        parse_str($response, $params);

        //debug
        //print_r($params);

        //set access token to session
        $_SESSION["access_token"] = $params["access_token"];
        return $params;

    }
    else
    {
        echo("The state does not match. You may be a victim of CSRF.");
        exit;
    }
}

function get_openid()
{
    $graph_url = "https://graph.qq.com/oauth2.0/me?access_token="
        . $_SESSION['access_token'];

    $str  = get_url_contents($graph_url);
    if (strpos($str, "callback") !== false)
    {
        $lpos = strpos($str, "(");
        $rpos = strrpos($str, ")");
        $str  = substr($str, $lpos + 1, $rpos - $lpos -1);
    }

    $user = json_decode($str);
    if (isset($user->error))
    {
        @header("content-Type: text/html; charset=UTF-8");
        echo "<h3>error:</h3>" . $user->error;
        echo "<h3>msg  :</h3>" . $user->error_description;
        echo '<h3><a href="/qqlogin">尝试再登录</a></h3>';
        exit;
    }

    //set openid to session
    $_SESSION["openid"] = $user->openid;
}

function get_user_info()
{
    $get_user_info = "https://graph.qq.com/user/get_user_info?"
        . "access_token=" . $_SESSION['access_token']
        . "&oauth_consumer_key=" . $_SESSION["appid"]
        . "&openid=" . $_SESSION["openid"]
        . "&format=json";

    $info = get_url_contents($get_user_info);
    $arr = json_decode($info, true);

    return $arr;
}

function get_info()
{
    $get_info = "https://graph.qq.com/user/get_info?"
        . "access_token=" . $_SESSION['access_token']
        . "&oauth_consumer_key=" . $_SESSION["appid"]
        . "&openid=" . $_SESSION["openid"]
        . "&format=json";

    $info = get_url_contents($get_info);
    $arr = json_decode($info, true);

    return $arr;
}

//QQ登录成功后的回调地址,主要保存access token
$cb = qq_callback();

//获取用户标示id
get_openid();

$openid = $_SESSION["openid"];
$expires = $timestamp - 20 + $cb["expires_in"];
$_SESSION["expires"] = $expires;

$db_openid = $DBS->fetch_one_array("SELECT `id`,`uid`,`name` FROM `yunbbs_qqweibo` WHERE `openid`='".$openid."'");

if($db_openid && $db_openid['uid']){
    // 如果微博信息为空尝试获取微博信息
    if(!$db_openid['name'] && strpos(' '.$_SESSION["scope"], 'get_info')){
        $user_info = get_info();
        if($user_info['data']['name']){
            $oid = $db_openid['id'];
            $name = $user_info['data']['name'];
            $DBM = new DB_MySQL;
            $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
            $DBM->unbuffered_query("UPDATE `yunbbs_qqweibo` SET `name` = '$name' WHERE `id`='$oid'");
        }
    }
    // 更新token 和 expires
    if(!isset($DBM)){
        $DBM = new DB_MySQL;
        $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
    }

    $DBM->unbuffered_query("UPDATE `yunbbs_qqweibo` SET `token` = '".$cb["access_token"]."',`expires` = '$expires' WHERE `id`='".$db_openid['id']."'");

    // 直接登录
    $cur_uid = $db_openid['uid'];
    $db_user = $DBS->fetch_one_array("SELECT * FROM `yunbbs_users` WHERE `id`='".$cur_uid."' LIMIT 1");
    if($db_user){
        $db_ucode = md5($db_user['id'].$db_user['password'].$db_user['regtime'].$db_user['lastposttime'].$db_user['lastreplytime']);
        //设置缓存和cookie
        $u_key = 'u_'.$cur_uid;
        $MMC->set($u_key, $db_user, 0, 600);
        setcookie('cur_uid', $cur_uid, $expires, '/');
        setcookie('cur_uname', $db_user['name'], $expires, '/');
        setcookie('cur_ucode', $db_ucode, $expires, '/');
        //$cur_user = $db_user;

        if($expires > $db_user["expires"]){
            $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `expires` = '$expires' WHERE `id`='".$cur_uid."'");
        }
        unset($db_user);
    }

    header("Location:/");
    exit;
}


///
/*
// 获取QQ 微博信息，可在QQ 登录接口通过审核后启用，
// 用QQ微博登录和用QQ登录略有不同，参见 http://youbbs.sinaapp.com/t/208
if(strpos(' '.$_SESSION["scope"], 'get_info')){
    $user_info = get_info();


    // $user_info['data']['head'] 头像 /100
    //$user_info['data']['name'] 微博地址 http://t.qq.com/#{name}
    //$user_info['data']['nick'] 网站名字
    // $user_info['data']['regtime'] 判断是否是新用户，至少三个月

    $regtime = intval($user_info['data']['regtime']);
    //
    //if(!$regtime || ($timestamp - $regtime)<7776000){
    if(!$regtime || ($timestamp - $regtime)<60){
        @header("content-Type: text/html; charset=UTF-8");
        echo '<h3>抱歉，您还没开通腾讯微博，请先去开通后再登录， <a href="http://t.qq.com" target="_blank">马上腾讯微博 http://t.qq.com</a> </h3>';
        echo '<h3><a href="/qqlogin">尝试再登录</a> 或用 <a href="/wblogin">新浪微博登录</a></h3>';
        echo '<h3><a href="/">返回首页</a></h3>';
        exit;
    }
    //

    $name = $user_info['data']['name'];

    $_SESSION["nick"] = $user_info['data']['nick'];
    if($user_info['data']['head']){
        $_SESSION["avatar"] = $user_info['data']['head'].'/100';
    }

}
// 获取QQ 微博信息 结束

*/

// 获取QQ 空间信息，在QQ 通过审核前使用

if(strpos(' '.$_SESSION["scope"], 'get_user_info') || strpos(' '.$_SESSION["scope"], 'get_info')){
    $user_info = get_user_info();

    // $user_info['figureurl_2'] 头像 100px
    // $user_info['nickname']

    $name = "";
    $_SESSION["nick"] = $user_info['nickname'];
    $_SESSION["avatar"] = $user_info['figureurl_2'];

    // 尝试获取微博信息
    if(strpos(' '.$_SESSION["scope"], 'get_info')){
        $user_info2 = get_info();
        if($user_info2['data']['name']){
            $name = $user_info2['data']['name'];
        }
        if($user_info2['data']['head']){
            $_SESSION["avatar"] = $user_info2['data']['head'].'/100';
        }
    }

}else{
    echo 'no info scope';
    exit;
}
// 获取QQ 空间信息 结束


if($db_openid){
    if($db_openid['uid']){
        // pass
    }else{
        header("Location:/qqsetname");
        exit;
    }
}else{

    $DBM = new DB_MySQL;
    $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);

    $DBM->query("INSERT INTO `yunbbs_qqweibo` (`id`,`uid`,`name`,`openid`,`token`,`expires`) VALUES (null,'0','$name', '$openid', '".$cb["access_token"]."', '".$expires."')");
    header("Location:/qqsetname");
    exit;

}

?>
