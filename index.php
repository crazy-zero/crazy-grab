<?php
/*
 * +----------------------------------------------------------------------
 * | xf9.com 幸福9号
 * +----------------------------------------------------------------------
 * | Copyright (c) 2014 http://www.xf9.com All rights reserved.
 * +----------------------------------------------------------------------
 * | Author: haowenhui <haowenhui@vacn.com.ccn>
 * +----------------------------------------------------------------------
 * | 公共入口文件
 */
$start = microtime(true);
# 开启调试模式
define("APP_DEBUG", 1);

# 设置系统路径
define('APP_PATH',realpath('./'));
# 引入核心文件
require "./System/Core.class.php";

$limnit = microtime(true) - $start;
echo 'Init core use time : ' . $limnit . PHP_EOL;

?>