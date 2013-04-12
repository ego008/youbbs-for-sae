<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

if (!$cur_user || $cur_user['flag']<99) exit('error: 403 Access Denied');

$tid = intval($_GET['tid']);
$query = "SELECT `id`,`cid`,`title`,`content`,`closecomment`,`visible` FROM `yunbbs_articles` WHERE `id`='$tid'";
$t_obj = $DBS->fetch_one_array($query);
if(!$t_obj){
    exit('404');
}

if($t_obj['closecomment']){
    $t_obj['closecomment'] = 'checked';
}else{
    $t_obj['closecomment'] = '';
}

if($t_obj['visible']){
    $t_obj['visible'] = 'checked';
}else{
    $t_obj['visible'] = '';
}

// 获取热点分类
$all_nodes = $MMC->get('all_nodes');
if(!$all_nodes){
    $query = $DBS->query("SELECT `id`, `name` FROM `yunbbs_categories` ORDER BY  `articles` DESC LIMIT 1000");
    $all_nodes = array();
    while($node = $DBS->fetch_array($query)) {
        $all_nodes[$node['id']] = $node['name'];
    }
    if( !array_key_exists($t_obj['cid'], $all_nodes) ){
        $cid = $t_obj['cid'];
        $c_obj = $DBS->fetch_one_array("SELECT `id`,`name` FROM `yunbbs_categories` WHERE `id`='".$cid."'");
        $all_nodes[$c_obj['id']] = $c_obj['name'];
    }
    $MMC->set('all_nodes', $all_nodes, 0 ,600);
    
    unset($node);
    $DBS->free_result($query);
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $old_cid = $t_obj['cid'];
    $p_cid = $_POST['select_cid'];
    $p_title = addslashes(trim($_POST['title']));
    $p_content = addslashes(trim($_POST['content']));
    $p_closecomment = intval($_POST['closecomment']);
    $p_visible = intval($_POST['visible']);
    
    if($p_title){
        $DBM = new DB_MySQL;
        $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
        
        $p_title = htmlspecialchars($p_title);
        $p_content = htmlspecialchars($p_content);
        $DBM->unbuffered_query("UPDATE `yunbbs_articles` SET `cid`='$p_cid',`title`='$p_title',`content`='$p_content',`closecomment`='$p_closecomment',`visible`='$p_visible' WHERE `id`='$tid'");
        $MMC->delete('t-'.$tid);
        $MMC->delete('t-'.$tid.'_ios');
        if($p_cid != $old_cid){
            $DBM->unbuffered_query("UPDATE `yunbbs_categories` SET `articles`=`articles`+1 WHERE `id`='$p_cid'");
            $DBM->unbuffered_query("UPDATE `yunbbs_categories` SET `articles`=`articles`-1 WHERE `id`='$old_cid'");
        }
        
        header('location: /t/'.$tid);
        exit;
    }else{
        $tip = '标题 不能留空';
    }
}else{
    $p_title = $t_obj['title'];
    $p_content = $t_obj['content'];
    $tip = '';
}
// 页面变量
$title = '修改帖子 - '.$t_obj['title'];
// 设置回复图片最大宽度
$img_max_w = 650;


$pagefile = ROOT . '/templates/default/'.$tpl.'admin-edit-post.php';

include(ROOT . '/templates/default/'.$tpl.'layout.php');

?>
