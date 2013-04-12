<?php
/**
 *程序官方支持社区 http://youbbs.sinaapp.com/
 *欢迎交流！
 *youBBS是开源项目，可自由修改，但要保留Powered by 链接信息
 */

//定义数据库信息，在SAE 平台上不用修改

//数据库主机名或IP 主
$servername_m = SAE_MYSQL_HOST_M;
//数据库主机名或IP 从
$servername_s = SAE_MYSQL_HOST_S;
//数据库用户名
$dbusername = SAE_MYSQL_USER;
//数据库密码
$dbpassword = SAE_MYSQL_PASS;
//数据库名
$dbname = SAE_MYSQL_DB;
//数据端口
$dbport = SAE_MYSQL_PORT;

//MySQL字符集
$dbcharset = 'utf8';
//系统默认字符集
$charset = 'utf-8';

// 定义缓存
$MMC = memcache_init();

?>