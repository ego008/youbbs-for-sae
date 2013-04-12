<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

//
// 屏蔽下面几行可以通过 用户名和密码 登录
if(($options['qq_appid'] && $options['qq_appkey']) || ($options['wb_key'] && $options['wb_secret'])){
    header("content-Type: text/html; charset=UTF-8");
    echo '<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
    <h3>请用第三方帐户登录本站 <br/><br/>';
    if($options['wb_key'] && $options['wb_secret']){
        echo '&nbsp;&nbsp;<a href="/wblogin"><img src="/static/weibo_login_55_24.png" alt="微博登录" title="用微博帐号登录"/></a>';
    }
    if($options['qq_appid'] && $options['qq_appkey']){
        echo '&nbsp;&nbsp;&nbsp;&nbsp;<a href="/qqlogin"><img src="/static/qq_logo_55_24.png" alt="QQ登录" title="用QQ登录"/></a>';
    }
    echo '&nbsp;<br/><br/><a href="/">返回首页</a></h3>';
    exit;
}
//

if($cur_user){
    // 如果已经登录用户无聊打开这网址就让他重新登录吧
    $MMC->delete('u_'.$cur_uid);
    setcookie("cur_uid", '', $timestamp-86400 * 365, '/');
    setcookie("cur_uname", '', $timestamp-86400 * 365, '/');
    setcookie("cur_ucode", '', $timestamp-86400 * 365, '/');
    $cur_user = null;
    $cur_uid = '';
}

$errors = array();
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    
    if(empty($_SERVER['HTTP_REFERER']) || $_POST['formhash'] != formhash() || preg_replace("/https?:\/\/([^\:\/]+).*/i", "\\1", $_SERVER['HTTP_REFERER']) !== preg_replace("/([^\:]+).*/", "\\1", $_SERVER['HTTP_HOST'])) {
    	exit('403: unknown referer.');
    }
    
    $name = addslashes(strtolower(trim($_POST["name"])));
    $pw = addslashes(trim($_POST["pw"]));
    if($name && $pw){
        if(strlen($name)<21 && strlen($pw)<32){
            if(preg_match('/^[a-zA-Z0-9\x80-\xff]{4,20}$/i', $name)){
                if(preg_match('/^[0-9]{4,20}$/', $name)){
                    $errors[] = '名字不能全为数字';
                }else{
                    // 检测输错超过5次即屏蔽该ip 1个小时
                    $ck_key = 'login_'.$onlineip;
                    $ck_obj = $MMC->get($ck_key);
                    if($ck_obj && $ck_obj > 5){
                        exit('error: 403');
                    }
                    $db_user = $DBS->fetch_one_array("SELECT * FROM `yunbbs_users` WHERE `name`='".$name."' LIMIT 1");
                    if($db_user){
                        $pwmd5 = md5($pw);
                        if($pwmd5 == $db_user['password']){
                            //设置缓存和cookie
                            $db_ucode = md5($db_user['id'].$db_user['password'].$db_user['regtime'].$db_user['lastposttime'].$db_user['lastreplytime']);
                            $cur_uid = $db_user['id'];
                            $MMC->set('u_'.$cur_uid, $check_user, 0, 600);
                            setcookie("cur_uid", $cur_uid, $timestamp+ 86400 * 365, '/');
                            setcookie("cur_uname", $name, $timestamp+86400 * 365, '/');
                            setcookie("cur_ucode", $db_ucode, $timestamp+86400 * 365, '/');
                            $cur_user = $db_user;
                            unset($db_user);
                            if($ck_obj){
                                $MMC->delete($ck_key);
                            }
                            header('location: /');
                            exit('logined');
                        }else{
                            // 用户名和密码不匹配
                            $errors[] = '用户名 或 密码 错误，出错不能超过5次';
                            if($ck_obj){
                                $MMC->increment($ck_key, 1);
                            }else{
                                $MMC->set($ck_key, 1, 0, 3600);
                            }
                        }
                    }else{
                        // 没有该用户名
                        $errors[] = '用户名 或 密码 错误，出错不能超过5次';
                        if($ck_obj){
                            $MMC->increment($ck_key, 1);
                        }else{
                            $MMC->set($ck_key, 1, 0, 3600);
                        }
                    }
                }
            }else{
                $errors[] = '名字 太长 或 太短 或 包含非法字符';
            }
        }else{
            $errors[] = '用户名 或 密码 太长了';
        }
    }else{
       $errors[] = '用户名 和 密码 必填'; 
    }
}

// 页面变量
$title = '登 录';

$pagefile = ROOT . '/templates/default/'.$tpl.'sigin_login.php';

include(ROOT . '/templates/default/'.$tpl.'layout.php');

?>
