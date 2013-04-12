<?php
/**
 * 发送微博
 */

define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

require(ROOT . '/config.php');
include(ROOT . '/common.php');
include(ROOT . "/api/qq_utils.php");


$biao = $_GET['biao'];
$uid = intval($_GET['uid']);
$id = intval($_GET['id']);

if($biao && $uid && $id){
    if($biao == "topic"){
        $query = "SELECT `title` FROM `yunbbs_articles` WHERE id='$id'";
        $obj = $DBS->fetch_one_array($query);
        $msg = $obj["title"];
        $url = 'http://'.$_SERVER['HTTP_HOST'].'/t/'.$id;
    }else{
        $query = "SELECT `articleid`,`content` FROM `yunbbs_comments` WHERE id='$id'";
        $obj = $DBS->fetch_one_array($query);
        $msg = mb_substr($obj["content"], 0, 120, 'utf-8');
        $url = 'http://'.$_SERVER['HTTP_HOST'].'/t/'.$obj["articleid"].'#'.$id;
    }
    
    $expnum = 0;
    // QQ 
    $db_openid = $DBS->fetch_one_array("SELECT `uid`,`openid`,`token`,`expires` FROM `yunbbs_qqweibo` WHERE `uid`='".$uid."'");
    if($db_openid && $db_openid["token"] && $db_openid["expires"]){
        if($db_openid["expires"] > $timestamp){
            $add_t_url  = "https://graph.qq.com/wb/add_weibo";
            $data = "access_token=".$db_openid["token"]
                ."&oauth_consumer_key=".$options['qq_appid']
                ."&openid=".$db_openid["openid"]
                ."&format=json"
                ."&type=1"
                ."&content=".urlencode($options['name'].' - '.$msg.' '.$url)
                //."&img=".urlencode($_POST["img"])
                ."&clientip=".$onlineip;
            
            //echo $data;
            $ret = do_post($add_t_url, $data); 
            //var_dump($ret);
            $retarr = json_decode($ret, true);
            if(in_array($retarr['errcode'], array(100014,100015,100030))){
                // 重新走登录流程
                if(!isset($DBM)){
                    $DBM = new DB_MySQL;
                    $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
                }
                $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `expires` = '0' WHERE `id`='".$uid."'");
            }
        }else{
            $expnum += 1;
        }
    }
    // weibo
    $db_openid = $DBS->fetch_one_array("SELECT `openid`,`token`,`expires` FROM `yunbbs_weibo` WHERE `uid`='".$uid."'");
    if($db_openid && $db_openid["token"] && $db_openid["expires"]){
        if($db_openid["expires"] > $timestamp){
            include( 'saetv2.ex.class.php' );
            $c = new SaeTClientV2( $options['wb_key'] , $options['wb_secret'] , $db_openid["token"] );
            $ret = $c->update( $options['name'].' - '.$msg.' '.$url );
            //$retarr = json_decode($ret, true);
            if ( isset($ret['error_code']) && $ret['error_code'] > 0 ) {
                //echo "<p>发送失败，错误：{$ret['error_code']}:{$ret['error']}</p>";
                if(in_array($ret['error_code'], array(21315,21319,21327))){
                    // 重新走登录流程
                    if(!isset($DBM)){
                        $DBM = new DB_MySQL;
                        $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
                    }
                    $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `expires` = '0' WHERE `id`='".$uid."'");
                }
            } else {
                //echo "<p>发送成功</p>";
            }
            
        }else{
            $expnum += 1;
        }
    }
    
    //
    if($expnum == 2){
        if(!isset($DBM)){
            $DBM = new DB_MySQL;
            $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
        }
        $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `expires` = '0' WHERE `id`='".$uid."'");
        
    }
}

?>