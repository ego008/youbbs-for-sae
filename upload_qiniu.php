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

// 限制小于10篇主贴的不能发图片
if($cur_user['articles']<=10){
    echo 'error: 403 articles < 10';
    exit;
}

if($options['close_upload']) exit('error: 403 附件上传已禁用');

// 七牛存储配置
// 七牛免费注册链接 http://t.cn/z8h5lsg
// 详细使用方法参见 http://www.ijd8.com/t/22
$bucket = '';
$accessKey = '';
$secretKey = '';

//

require_once(ROOT . "/qiniu/io.php");
require_once(ROOT . "/qiniu/rs.php");

//

$mw = intval($_GET['mw']);

$rsp = array('status'=>201, 'msg'=>'ok');

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if($_FILES['filetoupload']['size'] && $_FILES['filetoupload']['size'] < 1048576){
        // 上传的文件名
        $up_name = strtolower($_FILES['filetoupload']['name']);
        // 上传文件扩展名
        $ext_name = pathinfo($up_name, PATHINFO_EXTENSION);

        if($options['ext_list']){
            // 如果限制扩展名
            if(in_array($ext_name, explode(",", $options['ext_list']))){
                $pass = '1';
            }else{
                $pass = null;
                $rsp['msg'] = '该文件格式不允许上传，只支持'.$options['ext_list'];
            }
        }else{
            //$pass = '1';
            $pass = null;
        }

        if($pass){
            $is_img = null;

            // 尝试以图片方式处理
            $img_info = getimagesize($_FILES['filetoupload']['tmp_name']);
            if($img_info){
                //创建源图片
                if($img_info[2]==1){
                    $img_obj = imagecreatefromgif($_FILES['filetoupload']['tmp_name']);
                    $t_ext = 'gif';
                }else if($img_info[2]==2){
                    $img_obj = imagecreatefromjpeg($_FILES['filetoupload']['tmp_name']);
                    $t_ext = 'jpg';
                }else if($img_info[2]==3){
                    $img_obj = imagecreatefrompng($_FILES['filetoupload']['tmp_name']);
                    $t_ext = 'png';
                }
                //如果上传的文件是jpg/gif/png则处理
                if(isset($img_obj)){
                    // 是正确的图片格式
                    $is_img = '1';
                    $new_name = $timestamp.'.'.$t_ext;
                }else{
                    // 其它格式的图片
                    $rsp['msg'] = '该图片格式不支持，只支持jpg/gif/png';
                    // 直接取同扩展名
                    $new_name = $timestamp.'.'.$ext_name;
                }
            }else{
                // 非图片
                $rsp['msg'] = '上传的不是图片，只支持jpg/gif/png格式的图片';
                if(in_array($ext_name, array('jpg','jpeg','gif','png'))){
                    // 扩展名是图片，但不能用getimagesize识别，可能是改扩展名伪装
                    $new_name = $timestamp.'.bad-'.$ext_name;
                }else{
                    if(in_array($ext_name, array('php','htm','html'))){
                        $new_name = $timestamp.'.rename-'.$ext_name;
                    }else{
                        $new_name = $timestamp.'.'.$ext_name;
                    }
                }
            }


            ///保存
            $upload_dir = 'upload/'.$cur_uid;

            $upload_filename = $upload_dir.'/'.$new_name;

            if($is_img){
                // 是正确的图片文件

                // 判断是不是动态gif
                $is_gifs = null;
                if($img_info[2]==1){
                    $out_img = file_get_contents($_FILES['filetoupload']['tmp_name']);
                    if(strpos($out_img, chr(0x21).chr(0xff).chr(0x0b).'NETSCAPE2.0') !== FALSE){
                        $is_gifs = '1';
                    }
                }

                if(!$is_gifs){
                    if($img_info[0] > $mw){
                        $percent = $mw/$img_info[0];
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
                    // 添加水印
                    if($options['img_shuiyin']){
                        $textblack = imagecolorallocate($new_image, 155, 155, 155);
                        $shuiyin = $_SERVER['HTTP_HOST'];
                        $img_x = $new_w - (strlen($shuiyin)*8.5);
                        $img_y = $new_h-22;
                        imagestring($new_image, 4, $img_x, $img_y, $shuiyin, $textblack);
                    }
                }
                imagedestroy($img_obj);

                if($bucket && $accessKey && $secretKey){
                    // 上传到七牛
                    Qiniu_SetKeys($accessKey, $secretKey);
                    $putPolicy = new Qiniu_RS_PutPolicy($bucket);
                    $upToken = $putPolicy->Token(null);
                    list($ret, $err) = Qiniu_Put($upToken, $upload_filename, $out_img, null);
                    if ($err !== null) {
                        //var_dump($err);
                        $rsp['msg'] = '图片保存失败，请稍后再试';
                    } else {
                        //var_dump($ret);
                        $rsp['status'] = 200;
                        $rsp['url'] = 'http://'.$bucket.'.qiniudn.com/'.$upload_filename;
                        $rsp['msg'] = '图片已成功上传';
                    }
                    unset($out_img);
                }else{
                    // 上传到服务器 SAE 里失效
                    $rsp['msg'] = '请先配置七牛信息 bucket && accessKey && secretKey';
                }

                if(!$is_gifs){
                    imagedestroy($new_image);
                }

            }else{
                //$rsp['msg'] = '本站暂不支持上传非图片附件';
                //
                // 其它文件
                if($bucket && $accessKey && $secretKey){
                    // 上传到七牛
                    Qiniu_SetKeys($accessKey, $secretKey);
                    $putPolicy = new Qiniu_RS_PutPolicy($bucket);
                    $upToken = $putPolicy->Token(null);
                    list($ret, $err) = Qiniu_Put($upToken, $upload_filename, $_FILES['filetoupload']['tmp_name'], null);
                    if ($err !== null) {
                        //var_dump($err);
                        $rsp['msg'] = '附件保存失败，请稍后再试';
                    } else {
                        //var_dump($ret);
                        $rsp['status'] = 200;
                        $rsp['url'] = '附件：'.$up_name.' http://'.$bucket.'.qiniudn.com/'.$upload_filename;
                        $rsp['msg'] = '附件已成功上传';
                    }                    
                }else{
                    // 上传到服务器 SAE 里失效
                    $rsp['msg'] = '请先配置七牛信息 bucket && accessKey && secretKey';
                }
                //
            }
        }

    }else{
        $rsp['msg'] = '附件数据没有正确上传，或文件太大了';
    }

    header("Content-Type: text/html");
    echo json_encode($rsp);
}


?>
