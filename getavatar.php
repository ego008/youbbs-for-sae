<?php
echo 'test end';
exit;

error_reporting(0);
session_start();

if($_SESSION["avatar"]){
    $imgurl = $_SESSION["avatar"];
}else{
    $imgurl = 'http://app.qlogo.cn/mbloghead/7c75d2543998c8f963bc/100';
}

$opts = array(
  'http'=>array(
    'method'=>"GET",
    'header'=>"Accept-language: en\r\n" .
              "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6\r\n".
              "Referer: ".$imgurl."\r\n"
  )
);

$context = stream_context_create($opts);

$avatardata = file_get_contents($imgurl, false, $context);

$img_obj = imagecreatefromstring($avatardata);

if($img_obj !== false){
    // 缩略图比例
    $new_w = 73;
    $new_h = 73;
    
    $new_image = imagecreatetruecolor($new_w, $new_h);
    $bg = imagecolorallocate ( $new_image, 255, 255, 255 );
    imagefill ( $new_image, 0, 0, $bg );
    
    ////目标文件，源文件，目标文件坐标，源文件坐标，目标文件宽高，源宽高
    imagecopyresampled($new_image, $img_obj, 0, 0, 0, 0, $new_w, $new_h, 100, 100);
    imagedestroy($img_obj);
    
    ob_start();
    imagejpeg($new_image, NULL, 95);
    $out_img = ob_get_contents();
    ob_end_clean();
    
    header("Content-type:image/jpeg");
    echo $out_img;
    
    unset($out_img);
    
}else{
    echo 'no img2';
}



exit;
header("Content-type:image/jpeg");
echo $avatardata;

?>
