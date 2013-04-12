<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

if (!$cur_user || $cur_user['flag']<99) exit('error: 403 Access Denied');


$tip1 = '';
$tip2 = '';

$act = trim($_GET['act']);
$mid = intval(trim($_GET['mid']));

if($act=='pass' || $act=='active'){
    
    $DBM = new DB_MySQL;
    $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
    
    if($DBM->unbuffered_query("UPDATE `yunbbs_users` SET `flag`=5 WHERE `id`='$mid'")){
        //更新缓存
        $MMC->delete('u_'.$mid);
        if($act=='pass'){
            $tip1 = '已成功操作';
            $MMC->delete('flag1_users');
        }else{
            $tip2 = '已成功操作';
            $MMC->delete('flag0_users');
        }
        
    }else{
        if($act=='pass'){
            $tip1 = '数据库更新失败，修改尚未保存，请稍后再试';
        }else{
            $tip2 = '数据库更新失败，修改尚未保存，请稍后再试';
        }        
    }
    
}


// users表flag 列没加入到索引，如果用户上10万，获取下面用户有点慢，8~10秒

// 获取最近等待审核的用户
$userdb = $MMC->get('flag1_users');
if(!$userdb && $userdb !== ''){
    $query_sql = "SELECT `id`,`name`,`regtime` FROM `yunbbs_users` WHERE `flag`=1 ORDER BY `id` DESC LIMIT 10";
    $query = $DBS->query($query_sql);
    $userdb=array();
    while ($user = $DBS->fetch_array($query)) {
        // 格式化内容
        $user['regtime'] = showtime($user['regtime']);
        $userdb[] = $user;
    }
    if($userdb){
        $MMC->set('flag1_users', $userdb, 0, 300);
    }else{
        $MMC->set('flag1_users', '', 0, 300);
    }
}

// 获取最近被禁用的用户
$userdb2 = $MMC->get('flag0_users');
if(!$userdb2 && $userdb2 !== ''){
    $query_sql = "SELECT `id`,`name`,`regtime` FROM `yunbbs_users` WHERE `flag`=0 ORDER BY `id` DESC LIMIT 10";
    $query = $DBS->query($query_sql);
    $userdb2=array();
    while ($user = $DBS->fetch_array($query)) {
        // 格式化内容
        $user['regtime'] = showtime($user['regtime']);
        $userdb2[] = $user;
    }
    if($userdb2){
        $MMC->set('flag0_users', $userdb2, 0, 300);
    }else{
        $MMC->set('flag0_users', '', 0, 300);
    }
    
}

// 页面变量
$title = '用户管理';


$pagefile = ROOT . '/templates/default/'.$tpl.'admin-user.php';

include(ROOT . '/templates/default/'.$tpl.'layout.php');

?>
