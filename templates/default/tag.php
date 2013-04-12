<?php 
if (!defined('IN_SAESPOT')) exit('error: 403 Access Denied'); 

echo '
<div class="title">
    <a href="/">',$options['name'],'</a> &raquo; ',$title,'/',$taltol_page,' (',$tag_obj['articles'],')
</div>

<div class="main-box home-box-list">';

foreach($articledb as $article){
echo '
<div class="post-list">
    <div class="item-avatar"><a href="/member/',$article['uid'],'">';
    echo '<img src="/static/grey.gif" data-original="',$options['base_avatar_url'],'/',$article['uavatar'],'.jpg!normal" alt="',$article['author'],'" />';
echo '    </a></div>
    <div class="item-content">
        <h1><a href="/t/',$article['id'],'">',$article['title'],'</a></h1>
        <span class="item-date"><a href="/n/',$article['cid'],'">',$article['cname'],'</a>  •  <a href="/member/',$article['uid'],'">',$article['author'],'</a>';
if($article['comments']){
    echo ' •  ',$article['edittime'],' •  最后回复来自 <a href="/member/',$article['ruid'],'">',$article['rauthor'],'</a>';
}else{
    echo ' •  ',$article['addtime'];
}
echo '</span>
    </div>';
if($article['comments']){
    $gotopage = ceil($article['comments']/$options['commentlist_num']);
    if($gotopage == 1){
        $c_page = '';
    }else{
        $c_page = '/'.$gotopage;
    }
    echo '<div class="item-count"><a href="/t/',$article['id'],'">',$article['comments'],'</a></div>';
}
echo '    <div class="c"></div>
</div>';

}


if($tag_obj['articles'] > $options['list_shownum']){ 
echo '<div class="pagination">';
if($page>1){
echo '<a href="/tag/',$tag,'/',$page-1,'" class="float-left">&laquo; 上一页</a>';
}
if($page<$taltol_page){
echo '<a href="/tag/',$tag,'/',$page+1,'" class="float-right">下一页 &raquo;</a>';
}
echo '<div class="c"></div>
</div>';
}

echo '</div>';

?>