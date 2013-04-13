<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

if (!$cur_user) exit('error: 401 login please');
if ($cur_user['flag']==0){
    exit('error: 403 Access Denied');
}else if($cur_user['flag']==1){
    exit('error: 401 Access Denied');
}

$cid = intval($_GET['cid']);
if($cid<1){
    header('location: /');
    exit;
}else if($cid == 1){
    echo '403: spam info forbidden.';
    exit;
}


if($options['main_nodes']){
    $main_nodes_arr = explode(",", $options['main_nodes']);
    if(!in_array($cid, $main_nodes_arr)){
       $main_nodes_arr[] = $cid;
    }
    $main_nodes_str = implode(",", $main_nodes_arr);
    $query = $DBS->query("SELECT `id`, `name` FROM `yunbbs_categories` WHERE `id` in($main_nodes_str)");

    $main_nodes_arr = array();
    while($node = $DBS->fetch_array($query)) {
        $main_nodes_arr[$node['id']] = $node['name'];
    }

    unset($node);
    $DBS->free_result($query);
}


if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if(empty($_SERVER['HTTP_REFERER']) || $_POST['formhash'] != formhash() || preg_replace("/https?:\/\/([^\:\/]+).*/i", "\\1", $_SERVER['HTTP_REFERER']) !== preg_replace("/([^\:]+).*/", "\\1", $_SERVER['HTTP_HOST'])) {
    	exit('403: unknown referer.');
    }

    // 刚注册一小时内发帖不能超过5篇
    if( ($timestamp - $cur_user['regtime'])<3600){
        if($cur_user['articles']>5){
            echo '403: have a rest pls.';
            exit;
        }
    }

    if($cid == 1){
        echo '403: spam info forbidden.';
        exit;

    }


    $p_title = addslashes(trim($_POST['title']));
    $p_content = addslashes(trim($_POST['content']));

    if($cur_user['articles']>10){
        $send2wb = intval($_POST['send2wb']);
    }else{
        $send2wb = 1;
    }

    if($p_title =='test' || $p_title=='测试'){
        exit('403: no test anymore.');
    }

    $check_con = ' '.$p_title.$p_content;
    // spam_words
    if($options['spam_words'] && $cur_user['flag']<99){
        $spam_words_arr = explode(",", $options['spam_words']);
        foreach($spam_words_arr as $spam){
            if(strpos($check_con, $spam)){
                // has spam word
                if(!isset($DBM)){
                    $DBM = new DB_MySQL;
                    $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
                }
                $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `flag`='0' WHERE `id`='$cur_uid'");
                $MMC->delete('u_'.$cur_uid);
                unset($spam_words_arr, $check_con);
                exit('403: dont post any spam.');
            }
        }
        unset($spam_words_arr, $check_con);
    }


    if($options['main_nodes']){
        $cid = $_POST['select_cid'];
        if($cid == 1){
            echo '403: spam info forbidden.';
            exit;

        }
    }
    if(($timestamp - $cur_user['lastposttime']) > $options['article_post_space']){
        if($p_title){
            if(mb_strlen($p_title,'utf-8')<=$options['article_title_max_len'] && mb_strlen($p_content,'utf-8')<=$options['article_content_max_len']){
                $conmd5 = md5($p_title.$p_content);
                if($MMC->get('cm_'.$conmd5)){
                    $tip = '请勿发布相同的内容 或 灌水';
                }else{
                    $DBM = new DB_MySQL;
                    $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);

                    $p_title = htmlspecialchars($p_title);
                    $p_content = htmlspecialchars($p_content);
                    $DBM->query("INSERT INTO `yunbbs_articles` (`id`,`cid`,`uid`,`title`,`content`,`addtime`,`edittime`) VALUES (null,$cid,$cur_uid, '$p_title', '$p_content', $timestamp, $timestamp)");
                    $new_aid = $DBM->insert_id();
                    $DBM->unbuffered_query("UPDATE `yunbbs_categories` SET `articles`=`articles`+1 WHERE `id`='$cid'");
                    $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `articles`=`articles`+1, `lastposttime`=$timestamp WHERE `id`='$cur_uid'");
                    // 更新u_code
                    $cur_user['lastposttime'] = $timestamp;
                    //
                    $MMC->delete('u_'.$cur_uid);
                    $new_ucode = md5($cur_uid.$cur_user['password'].$cur_user['regtime'].$cur_user['lastposttime'].$cur_user['lastreplytime']);
                    if($cur_user['expires']){
                        $cookie_ex = $cur_user['expires'];
                    }else{
                        $cookie_ex = $timestamp+ 86400 * 365;
                    }
                    setcookie("cur_uid", $cur_uid, $cookie_ex, '/');
                    setcookie("cur_uname", $cur_uname, $cookie_ex, '/');
                    setcookie("cur_ucode", $new_ucode, $cookie_ex, '/');
                    // del cache
                    $MMC->delete('home-article-list');
                    $MMC->delete('cat-page-article-list-'.$cid.'-1');
                    $MMC->delete('n-'.$cid);
                    $MMC->delete('site_infos');
                    $MMC->delete('feed-article-list');
                    // mentions 没有提醒用户的id，等缓存自动过期，提醒有600秒延迟
                    $mentions = find_mentions(' '.$p_title.' '.$p_content, $cur_uname, $cur_uid);
                    if($mentions && count($mentions)<=10){
                        foreach($mentions as $m_name){
                            if(intval($m_name)){
                                $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `notic` =  concat('$new_aid,', `notic`) WHERE `id`='$m_name'");
                                $MMC->delete('u_'.$m_name);
                            }else{
                                if(strlen($m_name)>3){
                                    $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `notic` =  concat('$new_aid,', `notic`) WHERE `name`='$m_name'");
                                }
                            }
                        }
                    }

                    // send to weibo
                    if($send2wb && $cur_user['expires'] > $timestamp){
                        $queue = new SaeTaskQueue('default');
                        $queue->addTask("http://".$_SERVER['HTTP_HOST']."/task/sendmsg/topic/".$cur_uid."/".$new_aid);
                        $queue->push();
                    }

                    // set tags
                    if(!isset($queue)){
                        $queue = new SaeTaskQueue('default');
                    }
                    $queue->addTask("http://".$_SERVER['HTTP_HOST']."/task/fenci/".$new_aid);
                    $queue->push();

                    // auto ping google & baidu
                    $queue->addTask("http://".$_SERVER['HTTP_HOST']."/task/atping/".$new_aid);
                    $queue->push();


                    // 保存内容md5值
                    $MMC->set('cm_'.$conmd5, '1', 0, 3600);

                    $p_title = $p_content = '';
                    header('location: /t/'.$new_aid);
                    exit;
                }
            }else{
                $tip = '标题'.mb_strlen($p_title,'utf-8').' 或 内容'.mb_strlen($p_content,'utf-8').' 太长了';
            }
        }else{
            $tip = '标题 不能留空';
        }
    }else{
        $tip = '发帖最小间隔时间是 '.$options['article_post_space'].'秒';
    }
}else{
    $p_title = '';
    $p_content = '';
    $tip = '';
    $c_obj = $DBS->fetch_one_array("SELECT * FROM `yunbbs_categories` WHERE `id`='".$cid."'");
    if(!$c_obj){
        exit('error: 404');
    }
}
// 页面变量
$title = '发新帖子';
$meta_des = $options['name'].' - '.$c_obj['name'].' - 发新帖子';

// 设置处理图片的最大宽度
$img_max_w = 650;
$newpost_page = '1';

//$newest_nodes = get_newest_nodes();

$pagefile = ROOT . '/templates/default/'.$tpl.'newpost.php';

include(ROOT . '/templates/default/'.$tpl.'layout.php');

?>
