<?php
/**
 * 对以前没有设置tag的文章设置tag
 */

exit('not worked any more.');

define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

require(ROOT . '/config.php');
include(ROOT . '/common.php');

// 获取上次最后更新的文章id
$tid = $MMC->get('last_topicid');
if(!$tid){
    $tid = 1;
}

//
//$tid = intval($_GET['tid']);

if($tid <1 || $tid >= 586){
    $MMC->set('last_topicid', 586, 0, 600);
    exit('all done: '.$tid);
}

if($tid){
    $query = "SELECT `title`,`tags` FROM `yunbbs_articles` WHERE id='$tid'";
    $obj = $DBS->fetch_one_array($query);
    if($obj && !$obj["tags"]){
        //$msg = $obj["title"];
        
        $seg = new SaeSegment();
        $ret = $seg->segment($obj["title"], 1);
        
        //print_r($ret);
        
        // 名词95 
        //不及物谓词(谓宾结构“剃头”)171
        // 地名(名处词专指：“中国”)102
        
        if ($ret === false){
            // 失败
        }else{
            $mingci = array();
            foreach($ret as $fc){
                if($fc['word_tag'] == 95){
                    $mingci[] = $fc['word'];
                }
            }
            //print_r($mingci);
            $mingci = array_filter(array_unique($mingci));
            if($mingci){
                $DBM = new DB_MySQL;
                $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
                
                foreach($mingci as $tag){
                    $tag_obj  = $DBS->fetch_one_array("SELECT `id`,`articles`,`ids` FROM `yunbbs_tags` WHERE `name`='$tag'");
                    if(empty($tag_obj)) {
                        $DBM->query("INSERT INTO `yunbbs_tags` (`id`,`name`,`articles`,`ids`) VALUES (null,'$tag', '1', '$tid')");
                    } else {
                        if($tag_obj['ids']){
                            if( !strpos(' ,'.$tag_obj['ids'].',', ','.$tid.',') ){
                                $ids = $tid.','.$tag_obj['ids'];
                            }
                        }else{
                            $ids = $tid;
                        }
                        if(isset($ids)){
                            $DBM->unbuffered_query("UPDATE `yunbbs_tags` SET `articles`=`articles`+1, `ids`='$ids' WHERE `name`='$tag'");
                        }
                    }
                    
                }
                //
                $tags = implode(",", $mingci);
                $DBM->unbuffered_query("UPDATE `yunbbs_articles` SET `tags`='$tags' WHERE `id`='$tid'");
            }
            echo 'set tags done: '.$tid;
            $MMC->set('last_topicid', $tid + 1, 0, 600);
        }
    }else{
        exit('tags exist.');
    }
}

?>