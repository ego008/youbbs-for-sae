<?php
define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

include(ROOT . '/config.php');
include(ROOT . '/common.php');

include( 'saetv2.ex.class.php' );

$o = new SaeTOAuthV2( $options['wb_key'] , $options['wb_secret'] );

$code_url = $o->getAuthorizeURL( 'http://'.$_SERVER['HTTP_HOST'].'/wbcallback' );

header("Location:$code_url");
exit;

?>
