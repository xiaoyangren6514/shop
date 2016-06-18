<?php
/**
 * Created by PhpStorm.
 * User: zhonglq
 * Date: 2016/6/17
 * Time: 23:31
 */
header("Content-Type: text/html;charset=utf-8");
define('APP_DEBUG',true);//设置开发模式
define('CSS_URL','/shopEc/Home/Public/css/');
define('IMAGE_URL','/shopEc/Home/Public/images/');
define('JS_URL','/shopEc/Home/Public/js/');
define('ADMIN_CSS_URL','/shopEc/Admin/Public/css/');
define('ADMIN_IMAGE_URL','/shopEc/Admin/Public/img/');
//echo "hello world";
include "../ThinkPHP/ThinkPHP.php";