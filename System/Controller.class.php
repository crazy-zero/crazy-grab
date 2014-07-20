<?php
/*
 * +----------------------------------------------------------------------
 * | xf9.com 幸福9号
 * +----------------------------------------------------------------------
 * | Copyright (c) 2014 http://www.xf9.com All rights reserved.
 * +----------------------------------------------------------------------
 * | Author: haowenhui <haowenhui@vacn.com.cn>
 * +----------------------------------------------------------------------
 * | 调用控制器 系统级别公共方法
 */

namespace System;

class Controller {
	
	/**
	 * 委托调用层级
	 * @var integer
	 */
	private static  $_invokeLevel = 0;

	/**
	*  队列调用对象
	*/
	protected static $_queueObj = null;

	/**
	*  队列服务器cluster
	*/
	protected static $_queueServer = array();	

	/**
	 * 请求处理完毕, 结束当前程序并发送响应结果给客户端
	 * @param mixed $response  	响应结果
	 * @param integer $status 		响应状态
	 * @param array $messageArgs	消息替换参数列表,用于替换配置或者指定的消息内的占位符
	 * @param string $message 		响应消息文本, 用于指定展示给调用者的可视化文本
	 * @return void or array 如果当前处于api调用期间, 则不会终止程序, 会把响应数据返回给调用者
	 */
	final public function endResponse($response = null, $status = 0, $messageArgs=null, $message = null){
		$data['response'] = $response===null ? array() : $response;
		$data['status'] = intval($status);
		$data['message'] = $message!=null ? $message : ($status==0 ? 'ok' :  $status ) ;
		if($data['message']==null)
			$data['message']=C('ERROR');
		//如果当前指定了消息或者状态码率, 且指定了有效的消息替换参数, 则进行消息替换处理
		if($messageArgs!==null && $data['message']!='ok' && is_array($messageArgs) && sizeof($messageArgs)>0){
			$search = array();
			foreach (array_keys($messageArgs) as $v)
				$search[] ="{#{$v}}";
			$data['message'] = str_replace($search, array_values($messageArgs), $data['message']);
		}
		
		if(self::$_invokeLevel > 0)
			return $data;
		 echo $data;
		// die(json_encode($data));
	}	
}
?>
