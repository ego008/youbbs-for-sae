<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');


$tid = intval($_GET['tid']);
// 评论页数，默认是1
$page = intval($_GET['page']);

// 获取文章
if($is_spider || $tpl){
    $t_mc_key = 't-'.$tid.'_ios';
}else{
    $t_mc_key = 't-'.$tid;
}

$t_obj = $MMC->get($t_mc_key);
if(!$t_obj){
    $query = "SELECT a.id,a.cid,a.uid,a.ruid,a.title,a.content,a.tags,a.addtime,a.edittime,a.views,a.comments,a.closecomment,a.favorites,a.visible,u.avatar as uavatar,u.name as author
        FROM `yunbbs_articles` a
        LEFT JOIN `yunbbs_users` u ON a.uid=u.id
        WHERE a.id='$tid'";
    $t_obj = $DBS->fetch_one_array($query);
    if($t_obj){
        if(!$t_obj['visible']){
            if($cur_user && $cur_user['flag']>=99){
                exit('404: <a href="/">Go back HomePage</a> <a href="/admin-edit-post-'.$tid.'">Edit</a>');
            }else{
                header("HTTP/1.0 404 Not Found");
                header("Status: 404 Not Found");
                include(ROOT . '/404.html');
                exit;

            }
        }
    }else{
        header("HTTP/1.0 404 Not Found");
        header("Status: 404 Not Found");
        include(ROOT . '/404.html');
        exit;

    }
    //水区的贴子不能回复
    if($t_obj['cid'] == 1){
        $t_obj['closecomment'] = 1;
    }

    $t_obj['addtime'] = showtime($t_obj['addtime']);
    $t_obj['edittime'] = showtime($t_obj['edittime']);
    if($is_spider || $tpl){
        // 手机浏览和搜索引擎访问不用 jquery.lazyload
        $t_obj['content'] = set_content($t_obj['content'], 1);
    }else{
        $t_obj['content'] = set_content($t_obj['content']);
    }

    // 根据tag或取相关文章及相关tag
    // 设置相关文章数
    $post_relative_num = 10;
    $t_obj['relative_topics'] = '';
    $t_obj['relative_tags'] = '';

    $relative_ids = array();
    $relative_tags = array();
    $relative_topics = array();
    if($t_obj['tags']){
        $tag_list = explode(",", $t_obj['tags']);
        $new_tag_list = array();
        foreach($tag_list as $tag){
            $tag_obj = $DBS->fetch_one_array("SELECT * FROM `yunbbs_tags` WHERE `name`='".$tag."'");
            $new_tag_list[] = '<a href="/tag/'.$tag.'">'.$tag.'</a>';
            $relative_ids[] = $tag_obj['ids'];
        }
        // set new tags
        $t_obj['tags'] = implode(", ", $new_tag_list);
        unset($new_tag_list);

        $relative_ids = implode(",", $relative_ids);
        $relative_ids = str_replace(",".$tid.",", ",", $relative_ids);
        $relative_ids = explode(",", $relative_ids);
        $relative_ids = array_filter(array_unique($relative_ids));
        $relative_ids = array_diff($relative_ids, array($tid));
        if(count($relative_ids) > $post_relative_num){
            shuffle($relative_ids);
            $relative_ids = array_slice($relative_ids, 0, $post_relative_num);
        }
        // get post by relative_ids
        if($relative_ids){
            $relative_ids = implode(",", $relative_ids);
            $query_sql = "SELECT `id`,`title`,`tags`
                FROM `yunbbs_articles`
                WHERE `id` in(".$relative_ids.") AND `cid` > '1'";
            $query = $DBS->query($query_sql);
            while ($article = $DBS->fetch_array($query)) {
                //$relative_topics[$article['id']] = $article['title'];
                $relative_topics[] = array('id'=>$article['id'], 'title'=>$article['title']);
                $relative_tags[] = $article['tags'];
            }
            $t_obj['relative_topics'] = $relative_topics;

            $tags_str = implode(",", $relative_tags);
            $relative_tags = explode(",", $tags_str);
            $relative_tags = array_filter(array_unique($relative_tags));
            $relative_tags = array_diff($relative_tags, $tag_list);
            if($relative_tags){
                $new_tag_list = array();
                foreach($relative_tags as $tag){
                    $new_tag_list[] = '<a href="/tag/'.$tag.'">'.$tag.'</a>';
                }
                $t_obj['relative_tags'] = implode(" ", $new_tag_list);
                unset($tags_str,$new_tag_list);
            }
            $DBS->free_result($query);
        }
        unset($tag_list);
    }

    unset($relative_ids, $relative_topics, $relative_tags);

    // tags end, 真费劲，没有缓存最好不用

    $MMC->set($t_mc_key, $t_obj, 0, 300);
}
// 处理正确的评论页数
$taltol_page = ceil($t_obj['comments']/$options['commentlist_num']);
if($page<0){
    header('location: /t/'.$tid);
    exit;
}else if($page==1){
    header('location: /t/'.$tid);
    exit;
}else{
    if($page>$taltol_page){
        header('location: /t/'.$tid.'/'.$taltol_page);
        exit;
    }
}


// 处理提交评论
$tip = '';
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if(empty($_SERVER['HTTP_REFERER']) || $_POST['formhash'] != formhash() || preg_replace("/https?:\/\/([^\:\/]+).*/i", "\\1", $_SERVER['HTTP_REFERER']) !== preg_replace("/([^\:]+).*/", "\\1", $_SERVER['HTTP_HOST'])) {
    	exit('403: unknown referer.');
    }

    // 刚注册一小时内回帖不能超过10篇
    if( ($timestamp - $cur_user['regtime'])<3600){
        if($cur_user['replies']>10){
            echo '403: have a rest pls.';
            exit;
        }
    }

    $c_content = addslashes(trim($_POST['content']));

    if($cur_user['articles']>10){
        $send2wb = intval($_POST['send2wb']);
    }else{
        $send2wb = 1;
    }

    if(($timestamp - $cur_user['lastreplytime']) > $options['comment_post_space']){
        $c_con_len = mb_strlen($c_content,'utf-8');
        if($c_con_len>=$options['comment_min_len'] && $c_con_len<=$options['comment_max_len']){
            $conmd5 = md5($c_content);
            if($MMC->get('cm_'.$conmd5)){
                $tip = '请勿发布相同的内容 或 灌水';
            }else{
                $DBM = new DB_MySQL;
                $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);

                // spam_words
                if($options['spam_words'] && $cur_user['flag']<99){
                    $spam_words_arr = explode(",", $options['spam_words']);
                    $check_con = ' '.$c_content;
                    foreach($spam_words_arr as $spam){
                        if(strpos($check_con, $spam)){
                            // has spam word
                            $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `flag`='0' WHERE `id`='$cur_uid'");
                            $MMC->delete('u_'.$cur_uid);
                            exit('403: dont post any spam.');
                        }
                    }
                }

                $c_content = htmlspecialchars($c_content);
                $DBM->query("INSERT INTO `yunbbs_comments` (`id`,`articleid`,`uid`,`addtime`,`content`) VALUES (null,$tid, $cur_uid, $timestamp, '$c_content')");
                $new_rid = $DBM->insert_id();
                $DBM->unbuffered_query("UPDATE `yunbbs_articles` SET `ruid`='$cur_uid',`edittime`='$timestamp',`comments`=`comments`+1 WHERE `id`='$tid'");
                $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `replies`=`replies`+1,`lastreplytime`='$timestamp' WHERE `id`='$cur_uid'");
                // 更新u_code
                $new_ucode = md5($cur_uid.$cur_user['password'].$cur_user['regtime'].$cur_user['lastposttime'].$timestamp);
                if($cur_user['expires']){
                    $cookie_ex = $cur_user['expires'];
                }else{
                    $cookie_ex = $timestamp+ 86400 * 365;
                }
                setcookie("cur_uid", $cur_uid, $cookie_ex, '/');
                setcookie("cur_uname", $cur_uname, $cookie_ex, '/');
                setcookie("cur_ucode", $new_ucode, $cookie_ex, '/');
                $MMC->delete('u_'.$cur_uid);
                // del cache
                $MMC->delete('t-'.$tid);
                $MMC->delete('t-'.$tid.'_ios');
                $MMC->delete('home-article-list');
                $MMC->delete('cat-page-article-list-'.$t_obj['cid'].'-1');
                $MMC->delete('site_infos');

                $new_taltol_page = ceil(($t_obj['comments']+1)/$options['commentlist_num']);
                if($new_taltol_page == $taltol_page){
                    $MMC->delete('commentdb-'.$tid.'-'.$taltol_page);
                    $MMC->delete('commentdb-'.$tid.'_ios-'.$taltol_page);
                }

                // mentions 没有提醒用户的id，等缓存自动过期，提醒有点延迟
                $mentions = find_mentions($c_content.' @'.$t_obj['author'], $cur_uname, $cur_uid);
                if($mentions && count($mentions)<=10){
                    foreach($mentions as $m_name){
                        if(intval($m_name)){
                            $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `notic` =  concat('$tid,', `notic`) WHERE `id`='$m_name'");
                            $MMC->delete('u_'.$m_name);
                        }else{
                            if(strlen($m_name)>3){
                                $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `notic` =  concat('$tid,', `notic`) WHERE `name`='$m_name'");
                            }
                        }
                    }
                }

                // send to weibo
                if($send2wb && $cur_user['expires'] > $timestamp){
                    $queue = new SaeTaskQueue('default');
                    $queue->addTask("http://".$_SERVER['HTTP_HOST']."/task/sendmsg/reply/".$cur_uid."/".$new_rid);
                    $queue->push();
                }

                // 保存内容md5值
                $MMC->set('cm_'.$conmd5, '1', 0, 3600);

                // 跳到评论最后一页
                if($page<$new_taltol_page){
                    $c_content = '';
                    header('location: /t/'.$tid.'/'.$new_taltol_page);
                    exit;
                }else{
                    $cur_ucode = $new_ucode;
                    $formhash = formhash();
                }

                // 若不转向
                $c_content = '';
                $t_obj['edittime'] = showtime($timestamp);
                $t_obj['comments'] += 1;
            }
        }else{
            $tip = '评论内容字数'.$c_con_len.' 太少或太多 ('.$options['comment_min_len'].' - '.$options['comment_max_len'].')';
        }
    }else{
        $tip = '回帖最小间隔时间是 '.$options['comment_post_space'].'秒';
    }
}else{
    $c_content = '';
}

// 获取分类
$c_obj = $MMC->get('n-'.$t_obj['cid']);
if(!$c_obj){
    $c_obj = $DBS->fetch_one_array("SELECT * FROM `yunbbs_categories` WHERE `id`='".$t_obj['cid']."'");
    $MMC->set('n-'.$t_obj['cid'], $c_obj, 0, 3600);
}

// 获取评论
if($t_obj['comments']){
    if($page == 0) $page = 1;
    if($is_spider || $tpl){
        $c_mc_key = 'commentdb-'.$tid.'_ios-'.$page;
    }else{
        $c_mc_key = 'commentdb-'.$tid.'-'.$page;
    }
    $commentdb = $MMC->get($c_mc_key);
    if(!$commentdb){
        $query_sql = "SELECT c.id,c.uid,c.addtime,c.content,u.avatar as avatar,u.name as author
            FROM `yunbbs_comments` c
            LEFT JOIN `yunbbs_users` u ON c.uid=u.id
            WHERE c.articleid='$tid' ORDER BY c.id ASC LIMIT ".($page-1)*$options['commentlist_num'].",".$options['commentlist_num'];
        $query = $DBS->query($query_sql);
        $commentdb=array();
        while ($comment = $DBS->fetch_array($query)) {
            // 格式化内容
            $comment['addtime'] = showtime($comment['addtime']);
            if($is_spider || $tpl){
                // 手机浏览和搜索引擎访问不用 jquery.lazyload
                $comment['content'] = set_content($comment['content'], 1);
            }else{
                $comment['content'] = set_content($comment['content']);
            }
            $commentdb[] = $comment;
        }
        unset($comment);
        $DBS->free_result($query);
        $MMC->set($c_mc_key, $commentdb, 0, 300);
    }
}

// 增加浏览数
if(!isset($DBM)){
    $DBM = new DB_MySQL;
    $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
}
$DBM->unbuffered_query("UPDATE `yunbbs_articles` SET `views`=`views`+1 WHERE `id`='$tid'");

// 如果id在提醒里则清除
if ($cur_user && $cur_user['notic'] && strpos(' '.$cur_user['notic'], $tid.',')){
    if(!isset($DBM)){
        $DBM = new DB_MySQL;
        $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
    }
    $db_user = $DBS->fetch_one_array("SELECT * FROM `yunbbs_users` WHERE `id`='".$cur_uid."'");

    $n_arr = explode(',', $db_user['notic']);
    foreach($n_arr as $k=>$v){
        if($v == $tid){
            unset($n_arr[$k]);
            // 不用break 因为notice 里可能有重复id
        }
    }
    $new_notic = implode(',', $n_arr);
    $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `notic` = '$new_notic' WHERE `id`='$cur_uid'");
    $MMC->delete('u_'.$cur_uid);
    unset($n_arr);
    unset($new_notic);
}

// 判断文章是不是已被收藏
$in_favorites = '';
if ($cur_user){
    $user_fav = $MMC->get('favorites_'.$cur_uid);
    if(!$user_fav){
        $user_fav = $DBS->fetch_one_array("SELECT * FROM `yunbbs_favorites` WHERE `uid`='".$cur_uid."'");
        $MMC->set('favorites_'.$cur_uid, $user_fav, 0, 300);
    }

    if($user_fav && $user_fav['content']){
        if( strpos(' ,'.$user_fav['content'].',', ','.$tid.',') ){
            $in_favorites = '1';
        }
    }
}

// 页面变量
$title = $t_obj['title'].' - '.$options['name'];
$newest_nodes = get_newest_nodes();
if(count($newest_nodes)==$options['newest_node_num']){
    $bot_nodes = get_bot_nodes();
}

//$links = get_links();
$meta_des = $c_obj['name'].' - '.$t_obj['author'].' - '.htmlspecialchars(mb_substr($t_obj['content'], 0, 150, 'utf-8'));

// 设置回复图片最大宽度
$img_max_w = 590;

if($page >1){
    $canonical = '/t/'.$t_obj['id'].'/'.$page;
}else{
    $canonical = '/t/'.$t_obj['id'];
}

$show_sider_ad = "1";

$pagefile = ROOT . '/templates/default/'.$tpl.'postpage.php';

include(ROOT . '/templates/default/'.$tpl.'layout.php');

?>
