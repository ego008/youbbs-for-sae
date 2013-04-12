<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

// 
$via = $_GET['via'];
if($via && $is_mobie){
    setcookie('vtpl', $via, $timestamp+86400 * 365, '/');
}
header('location: /');
exit;

?>
