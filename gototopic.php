<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

$tid = $_GET['tid'];
$db_user = $DBS->fetch_one_array("SELECT * FROM `yunbbs_users` WHERE `id`='".$cur_uid."' LIMIT 1");

if($db_user['notic']){
    $n_arr = explode(',', $db_user['notic']);
    foreach($n_arr as $k=>$v){
        if($v == $tid){
            unset($n_arr[$k]);
            // 不用break 因为notice 里可能有重复id
        }
    }
    $new_notic = implode(',', $n_arr);
    $DBM = new DB_MySQL;
    $DBM->connect($servername_m, $dbport, $dbusername, $dbpassword, $dbname);
    $DBM->unbuffered_query("UPDATE `yunbbs_users` SET `notic` = '$new_notic' WHERE `id`='$cur_uid'");
    $MMC->delete('u_'.$cur_uid);
    unset($n_arr);
    unset($new_notic);
}
header('location: /t/'.$tid);
exit;
?>
