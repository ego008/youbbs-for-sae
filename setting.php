<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

require(ROOT . '/config.php');
require(ROOT . '/common.php');

if (!$cur_user) exit('error: 401 login please');
if ($cur_user['flag']==0){
    header("content-Type: text/html; charset=UTF-8");
    exit('error: 403 该帐户已被禁用');
}else if($cur_user['flag']==1){
    header("content-Type: text/html; charset=UTF-8");
    exit('error: 401 该帐户还在审核中');
}

$tip1 = '';
$tip2 = '';
$tip3 = '';
$av_time = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $action = $_POST['action'];
    if($action == 'info'){
        $email = addslashes(filter_chr(trim($_POST['email'])));
        $url = char_cv(filter_chr(trim($_POST['url'])));
        $about = addslashes(trim($_POST['about']));
        
        $DBM = new DB_MySQL;
        $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
        
        if($DBM->unbuffered_query("UPDATE `yunbbs_users` SET `email`='$email', `url`='$url', `about`='$about' WHERE `id`='$cur_uid'")){
            //更新缓存
            $cur_user['email'] = $email;
            $cur_user['url'] = $url;
            $cur_user['about'] = $about;
            $MMC->set('u_'.$cur_uid, $cur_user, 0, 600);
            $tip1 = '已成功保存';
        }else{
            $tip1 = '数据库更新失败，修改尚未保存，请稍后再试';
        }
    }else if($action == 'avatar'){
        if($_FILES['avatar']['size'] && $_FILES['avatar']['size'] < 301000){
            $img_info = getimagesize($_FILES['avatar']['tmp_name']);
            if($img_info){
                //创建源图片
                if($img_info[2]==1){
                    $img_obj = imagecreatefromgif($_FILES['avatar']['tmp_name']);
                }else if($img_info[2]==2){
                    $img_obj = imagecreatefromjpeg($_FILES['avatar']['tmp_name']);
                }else if($img_info[2]==3){
                    $img_obj = imagecreatefrompng($_FILES['avatar']['tmp_name']);
                }
                //如果上传的文件是jpg/gif/png则处理
                if(isset($img_obj)){
                    // 缩略图比例
                    $max_px = max($img_info[0], $img_info[1]);
                    //large
                    if($max_px>73){
                        $percent = 73/$max_px;
                        $new_w = round($img_info[0]*$percent);
                        $new_h = round($img_info[1]*$percent);
                    }else{
                        $new_w = $img_info[0];
                        $new_h = $img_info[1];
                    }
                    
                    $new_image = imagecreatetruecolor($new_w, $new_h);
                    $bg = imagecolorallocate ( $new_image, 255, 255, 255 );
                    imagefill ( $new_image, 0, 0, $bg );
                    
                    ////目标文件，源文件，目标文件坐标，源文件坐标，目标文件宽高，源宽高
                    imagecopyresampled($new_image, $img_obj, 0, 0, 0, 0, $new_w, $new_h, $img_info[0], $img_info[1]);
                    imagedestroy($img_obj);
                    
                    // 上传到又拍云
                    include(ROOT.'/upyun.class.php');
                    ob_start();
                    imagejpeg($new_image, NULL, 95);
                    $out_img = ob_get_contents();
                    ob_end_clean();
                    $upyun = new UpYun($options['upyun_avatar_domain'], $options['upyun_user'], $options['upyun_pw']);
                    // 本地调试失败
                    if($upyun->writeFile('/'.$cur_uid.'.jpg', $out_img)){
                        if($cur_user['avatar']!=$cur_user['id']){
                            $DBM = new DB_MySQL;
                            $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
                            if($DBM->unbuffered_query("UPDATE `yunbbs_users` SET `avatar`='$cur_uid' WHERE `id`='$cur_uid'")){
                                $cur_user['avatar'] = $cur_user['id'];
                                $MMC->set('u_'.$cur_uid, $cur_user, 0, 600);
                            }else{
                                $tip2 = '数据保存失败，请稍后再试';
                            }
                        }
                    }else{
                        $tip2 = '图片保存失败，请稍后再试';
                    }
                    unset($out_img);
                    
                    //
                
                    $av_time = $timestamp;
                }else{
                    $tip2 = '图片转换失败，请稍后再试';
                }
                
            }else{
                $tip2 = '你上传的不是图片文件，只支持jpg/gif/png三种格式';
            }
        }else{
            $tip2 = '图片尚未上传或太大了';
        }
    }else if($action == 'chpw'){
        $password_current = addslashes(trim($_POST['password_current']));
        $password_new = addslashes(trim($_POST['password_new']));
        $password_again = addslashes(trim($_POST['password_again']));
        if($password_current && $password_new && $password_again){
            if($password_new == $password_again){
                if(md5($password_current) == $cur_user['password']){
                    if($password_current != $password_new){
                        $new_md5pw = md5($password_new);
                        
                        $DBM = new DB_MySQL;
                        $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
                        
                        if($DBM->unbuffered_query("UPDATE `yunbbs_users` SET `password`='$new_md5pw' WHERE `id`='$cur_uid'")){
                            //更新缓存和cookie
                            $cur_user['password'] = $new_md5pw;
                            $MMC->set('u_'.$cur_uid, $cur_user, 0, 600);
                            $new_ucode = md5($cur_uid.$new_md5pw.$cur_user['regtime'].$cur_user['lastposttime'].$cur_user['lastreplytime']);
                            if($cur_user['expires']){
                                $cookie_ex = $cur_user['expires'];
                            }else{
                                $cookie_ex = $timestamp+ 86400 * 365;
                            }
                            setcookie("cur_uid", $cur_uid, $cookie_ex, '/');
                            setcookie("cur_uname", $cur_uname, $cookie_ex, '/');
                            setcookie("cur_ucode", $new_ucode, $cookie_ex, '/');
                            $tip3 = '密码已成功更改，请记住新密码';
                        }else{
                            $tip3 = '数据保存失败，请稍后再试';
                        }
                    }else{
                        $tip3 = '输入的新密码和原来的密码相同，没修改！';
                    }
                }else{
                    $tip3 = '输入的当前密码不正确';
                }
            }else{
                $tip3 = '新密码、重复新密码不一致';
            }
        }else{
            $tip3 = '请填写完整，当前密码、新密码、重复新密码';
        }
    }else if($action == 'setpw'){
        $password_new = addslashes(trim($_POST['password_new']));
        $password_again = addslashes(trim($_POST['password_again']));
        if($password_new && $password_again){
            if($password_new == $password_again){
                $new_md5pw = md5($password_new);
                
                $DBM = new DB_MySQL;
                $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
                
                if($DBM->unbuffered_query("UPDATE `yunbbs_users` SET `password`='$new_md5pw' WHERE `id`='$cur_uid'")){
                    //更新缓存和cookie
                    $cur_user['password'] = $new_md5pw;
                    $MMC->set('u_'.$cur_uid, $cur_user, 0, 600);
                    $new_ucode = md5($cur_uid.$new_md5pw.$cur_user['regtime'].$cur_user['lastposttime'].$cur_user['lastreplytime']);
                    if($cur_user['expires']){
                        $cookie_ex = $cur_user['expires'];
                    }else{
                        $cookie_ex = $timestamp+ 86400 * 365;
                    }
                    setcookie("cur_uid", $cur_uid, $cookie_ex, '/');
                    setcookie("cur_uname", $cur_uname, $cookie_ex, '/');
                    setcookie("cur_ucode", $new_ucode, $cookie_ex, '/');
                    $tip3 = '登录密码已成功设置，请记住登录密码';
                }else{
                    $tip3 = '数据保存失败，请稍后再试';
                }
            }else{
                $tip3 = '登录密码、重复密码不一致';
            }
        }else{
            $tip3 = '请填写完整，登录密码、重复密码';
        }
    }
}

// 页面变量
$title = '设置';
$meta_des = $options['name'].' - 用户设置';

$newest_nodes = get_newest_nodes();

$pagefile = ROOT . '/templates/default/'.$tpl.'setting.php';

include(ROOT . '/templates/default/'.$tpl.'layout.php');;

?>