<?php
if (!defined('IN_SAESPOT')) exit('error: 403 Access Denied');

echo '
<form action="',$_SERVER["REQUEST_URI"],'" method="post">
<input type="hidden" name="formhash" value="',$formhash,'" />
<div class="title">
    <a href="/">',$options['name'],'</a> &raquo; ';
if($options['main_nodes']){
    echo '<select name="select_cid">';
    foreach($main_nodes_arr as $n_id=>$n_name){
        if($cid == $n_id){
            $sl_str = ' selected="selected"';
        }else{
            $sl_str = '';
        }
        echo '<option value="',$n_id,'"',$sl_str,'>',$n_name,'</option>';
    }
    echo '</select>';
}else{
    echo '    <a href="/n/',$c_obj['id'],'">',$c_obj['name'],'</a> (',$c_obj['articles'],')';
}
echo '
     - 发新帖
</div>

<div class="main-box">';

if($c_obj['about']){
    echo '<p class="grey"> • ',$c_obj['about'],'</p>';
}

if($tip){
    echo '<p class="red">',$tip,'</p>';
}
echo '

<p>
<input type="text" name="title" value="',htmlspecialchars($p_title),'" class="sll" />
</p>
<div class="grey fs12">（如果标题已表述清楚，内容可以留空）</div>
<p><textarea id="id-content" name="content" class="mll tall">',htmlspecialchars($p_content),'</textarea></p>
';
if(!$options['close_upload'] && $cur_user['articles']>10){
    include(ROOT . '/templates/default/upload.php');
}else{
    echo '<div class="float-right grey fs12">发满11个帖才能上传图片，可先贴微博里图片的url</div>';
}
echo '
<p><div class="float-left">
<input type="submit" value=" 发 表 " name="submit" class="textbtn" />';

echo '
&nbsp;&nbsp;&nbsp;&nbsp;
<label class="grey fs12"><input type="checkbox" name="send2wb" value="1" checked /> 同时发送到微博</label>
';

echo '
</div><div class="c"></div></p>
</form>

</div>';


?>