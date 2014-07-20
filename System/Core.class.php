<?php
/*
 * +----------------------------------------------------------------------
 * | xf9.com 幸福9号
 * +----------------------------------------------------------------------
 * | Copyright (c) 2014 http://www.xf9.com All rights reserved.
 * +----------------------------------------------------------------------
 * | Author: haowenhui <haowenhui@vacn.com.ccn>
 * +----------------------------------------------------------------------
 * | 核心文件 负责载入框架的所有文件和处理基本业务逻辑
 */

# URL 模式定义
const URL_COMMON        =   0;  # 普通模式

# API类文件后缀
const EXT               =   '.class.php';

# 系统常量定义
defined('APP_DEBUG') 	or define('APP_DEBUG',      false);										# 是否调试模式
defined('CORE_PATH') 	or define('CORE_PATH',     __DIR__.'/');								# 系统目录 System
defined('DATA_PATH')     or define('DATA_PATH',     CORE_PATH.'/Data/');						# 数据层目录
defined('API_PATH') 	or define('API_PATH',       CORE_PATH.'Api/');                          # 项目路径 System/Api/
defined('LIB_PATH')     or define('LIB_PATH',       realpath('./Library').'/');					# 系统核心类库目录 
defined('COMMON_PATH')  or define('COMMON_PATH',    APP_PATH.'/Common/');						# 应用公共目录
defined('CONF_PATH')    or define('CONF_PATH',      APP_PATH.'/Conf/');							# 应用配置目录
defined('LOG_PATH')     or define('LOG_PATH',       APP_PATH.'/Logs/');							# 应用日志目录
defined('CALL_PATH')     or define('CALL_PATH',       APP_PATH.'/Api/');						# API分组调用路径



# 引入初始化类
require CORE_PATH.'Start'.EXT;


# 引入异常处理类
require CORE_PATH.'Exception'.EXT;
require CORE_PATH.'Error'.EXT;

# 引入基本类库
require CORE_PATH.'Model'.EXT;
require CORE_PATH.'Controller'.EXT;

# 初始化系统
System\Start::initialize();

?>
