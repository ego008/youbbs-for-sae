<?php 
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

include( 'saetv2.ex.class.php' );

error_reporting(0);
session_start();

$o = new SaeTOAuthV2( $options['wb_key'] , $options['wb_secret'] );

if (isset($_REQUEST['code'])) {
    $keys = array();
    $keys['code'] = $_REQUEST['code'];
    $keys['redirect_uri'] = 'http://'.$_SERVER['HTTP_HOST'].'/wbcallback';
    try {
        $token = $o->getAccessToken( 'code', $keys ) ;
    } catch (OAuthException $e) {
        //
    }
}

if ($token) {
    //$_SESSION['token'] = $token;
    //setcookie( 'weibojs_'.$o->client_id, http_build_query($token) );
    //
    //var_dump($token);
    /*
     $token
     array(4) {
       ["access_token"]=>
       string(32) "2.00_1hs1B0xxttu14ec6eedxxxxxx"
       ["remind_in"]=>
       string(9) "157679999"
       ["expires_in"]=>
       int(157679999)
       ["uid"]=>
       string(10) "12246xxxxx"
     }
     
     */
    $openid = $token['uid'];
    $_SESSION["openid"] = $openid;
    $expires = $timestamp - 20 + $token["expires_in"];
    $_SESSION["expires"] = $expires;
    
    $db_openid = $DBS->fetch_one_array("SELECT `id`,`uid`,`name` FROM `yunbbs_weibo` WHERE `openid`='".$openid."'");
    
    if($db_openid && $db_openid['uid']){
        
        // 更新token 和 expires
        if(!isset($DBM)){
            $DBM = new DB_MySQL;
            $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
        }
        $DBM->unbuffered_query("UPDATE `yunbbs_weibo` SET `token` = '".$token["access_token"]."',`expires` = '$expires' WHERE `id`='".$db_openid['id']."'");
        
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
    
    /// 获取用户信息
    $c = new SaeTClientV2( $options['wb_key'] , $options['wb_secret'] , $token['access_token'] );
    $uid_get = $c->get_uid();
    $uid = $uid_get['uid'];
    $user_info = $c->show_user_by_id( $uid);//根据ID获取用户等基本信息
    //var_dump($user_info);
    //
    /*
     * $user_message
     * screen_name 用户昵称
     * profile_url 用户的微博统一URL地址
     * avatar_large 用户大头像地址
     */
    ///
    
    // 尝试获取用户信息（通过审核才能获取）
    $name = $user_info['profile_url'];
    $_SESSION["nick"] = $user_info['name'];
    if($user_info['avatar_large']){
        $_SESSION["avatar"] = $user_info['avatar_large'];
    }
    
    if($db_openid){
        if($db_openid['uid']){
            // pass
        }else{
            header("Location:/wbsetname");
            exit;
        }
    }else{
    
        $DBM = new DB_MySQL;
        $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
        
        $DBM->query("INSERT INTO `yunbbs_weibo` (`id`,`uid`,`name`,`openid`,`token`,`expires`) VALUES (null,'0','$name', '$openid', '".$token["access_token"]."', '".$expires."')");
        header("Location:/wbsetname");
        exit;
        
    }
    
} else {
    echo 'Get token failed. <a href="/">Go back Home</a>';
    exit;
}


?>
