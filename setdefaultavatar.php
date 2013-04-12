<?php
/**
 * 设置默认头像，把默认头像 /static/default-avatar.png 上传到又拍云
 */

define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

require(ROOT . '/config.php');
require(ROOT . '/common.php');

if (!$cur_user || $cur_user['flag']<99) exit('error: 403 Access Denied');

$tip = "";

if($options['upyun_avatar_domain'] && $options['upyun_user'] && $options['upyun_pw']){
    // 上传到又拍云
    $out_img = file_get_contents('./static/default-avatar.png');
    include(ROOT.'/upyun.class.php');
    
    $upyun = new UpYun($options['upyun_avatar_domain'], $options['upyun_user'], $options['upyun_pw']);
    
    if($upyun->writeFile('/0.jpg', $out_img)){
        $tip = '默认头像设置成功';
    }else{
        $tip = '图片保存失败，请稍后再试';
    }
    unset($out_img);    
}else{
    $tip = '还没设定头像空间名称和操作用户和密码';
}
                    


@header("content-Type: text/html; charset=UTF-8");
echo '<h3>',$tip,'</h3>';
echo '<h4><a href="/">返回首页</a></h4>';

?>