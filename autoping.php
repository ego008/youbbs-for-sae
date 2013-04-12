<?php
/**
 * 通过分词设置tag
 */

define('IN_SAESPOT', 1);
define('ROOT' , pathinfo(__FILE__, PATHINFO_DIRNAME));

$tid = intval($_GET['tid']);

if(!$tid){
    echo 'no tid';
    exit;
}

$site_name = $options["name"];
$site_url = "http://".$_SERVER["HTTP_HOST"];
$article_url = $site_url."/t/".$tid;
$site_feed = $site_url."/feed";

$baiduXML = <<<EOT
    <?xml version="1.0" encoding="UTF-8"?>
    <methodCall>
    <methodName>weblogUpdates.extendedPing</methodName>
    <params>
    <param><value><string>$site_name</string></value></param>
    <param><value><string>$site_url</string></value></param>
    <param><value><string>$article_url</string></value></param>
    <param><value><string>$site_feed</string></value></param>
    </params>
    </methodCall>
EOT;

$googleXML = <<<END
<?xml version="1.0" encoding="UTF-8"?>
<methodCall>
  <methodName>weblogUpdates.extendedPing</methodName>
  <params>
    <param>
      <value>$site_name</value>
    </param>
    <param>
      <value>$site_url</value>
    </param>
    <param>
      <value>$article_url</value>
    </param>
    <param>
      <value>$site_feed</value>
    </param>
  </params>
</methodCall>
END;

echo $googleXML.'<br/><br/><br/>';

function postUrl($url, $postvar) {
    $ch = curl_init();
    $headers = array(
            'User-Agent: request',
            'Content-Type: text/xml',
            'Content-length: '.strlen($postvar)
        );
    
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_0);
    curl_setopt($ch,CURLOPT_TIMEOUT,5);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLINFO_HEADER_OUT, true);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$postvar); 
    
    $res = curl_exec ($ch);
    curl_close ($ch);
    return $res;
}

$res = postUrl('http://blogsearch.google.com/ping/RPC2', $googleXML);
//下面是返回成功与否的判断（根据谷歌ping的接口说明）
if (strpos($res, "<boolean>0</boolean>")){
    echo "PING Google 成功 ";
}else{
    echo "PING Google 失败 ";
}

echo $res;

$res = postUrl('http://ping.baidu.com/ping/RPC2', $baiduXML);
//下面是返回成功与否的判断（根据百度ping的接口说明）
if (strpos($res, "<int>0</int>")){
    echo "PING Baidu 成功 ";
}else{
    echo "PING Baidu 失败 ";
}

echo $res;

?>