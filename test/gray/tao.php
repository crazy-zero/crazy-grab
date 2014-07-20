<?php
/**
 * 扎取天猫健康保健品类的团单
 */
$page = 1;//当前页码;
$totalPage = 0;//总页码;
do {
    $s = ($page - 1) * 95;
    $url = "http://list.taobao.com/itemlist/market/food2011.htm?_input_charset=utf-8&json=on&atype=b&cat=50008825&s=".$s."&style=grid&as=0&viewIndex=1&spm=a2106.2206569.0.0.M8WiNf&same_info=1&isnew=2&pSize=95&_ksTS=1405235037780_20";
    //获取json数据
    $json = HttpGet($url);
    //处理编码问题
    $utf8_json = characet($json);
    //json解析为数组
    $arr = json_decode($utf8_json, true);
    //获取页码
    $page = $arr['page']['currentPage'];
    //获取总页码
    $totalPage = $arr['page']['totalPage'];
    //获取商品列表
    $lists = $arr['itemList'];
    //计算总共商品数
    $count = count($lists);
    //向终端输出信息
    echo "获取....第".$page."页,共".$count."条信息\n";
    //开始插入数据库
    $insert_num = insert_db($lists);
    //向终端输出信息
    echo "\n";
    echo "成功".$insert_num."条 失败".($count-$insert_num)."条\n\n";    
    $page++;
} while ( $page <= $totalPage);




/**
 * 插入数据库
 * @param  [type] $data [description]
 * @return [type] $inser_num [返回成功插入的数量]
 */
function insert_db($data) {
	echo "开始写入数据库\n";
    $insert_num = 0;//成功插入的数量
    $con = new mysqli("localhost","root","", "collection");
    if (!$con){
        die('连接数据库失败:' . $con->error() . "\n\n");
    }
    $con->query('SET NAMES UTF8');
    foreach ($data as $v) { 
        $sql = 'INSERT INTO tao (title, tip, price, currentPrice, unitPrice, unit, loc, href) VALUES ("'.$v["title"].'", "'.$v['tip'].'", "'.$v['price'].'", "'.$v['currentPrice'].'", "'.$v['unitPrice'].'", "'.$v['unit'].'", "'.$v['loc'].'", "'.$v['href'].'")';
        $res = $con->query($sql);
        if(!$res){
            echo "插入失败！商品地址：".$v['href']."\n"; 
            echo iconv("UTF-8","gb2312",$sql)."\n";
        }else{
			echo ".";
            $insert_num++;
        }
    }
    $con->close();
    return $insert_num;
}

/**
 * 发送请求并获取返回的信息
 * @param $url
 */
function HttpGet($url)
{
    $ch = curl_init();  
    $timeout = 5;  
    curl_setopt ($ch, CURLOPT_URL, $url);  
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);  
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);  
    $file_contents = curl_exec($ch);  
    curl_close($ch);  
    return $file_contents; 
}
/**
 * 自动将编码转为UTF-8
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function characet($data){
  if( !empty($data) ){
    $fileType = mb_detect_encoding($data , array('UTF-8','GBK','LATIN1','BIG5')) ;
    if( $fileType != 'UTF-8'){
      $data = mb_convert_encoding($data ,'utf-8' , $fileType);
    }
  }
  return $data;
}

/**
 * 浏览器友好的变量输出
 * @param mixed $var 变量
 * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
 * @param string $label 标签 默认为空
 * @param boolean $strict 是否严谨 默认为true
 * @return void|string
 */
function dump($var, $echo=true, $label=null, $strict=true) {
    $label = ($label === null) ? '' : rtrim($label) . ' ';
    if (!$strict) {
        if (ini_get('html_errors')) {
            $output = print_r($var, true);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        } else {
            $output = $label . print_r($var, true);
        }
    } else {
        ob_start();
        var_dump($var);
        $output = ob_get_clean();
        if (!extension_loaded('xdebug')) {
            $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        }
    }
    if ($echo) {
        echo($output);
        return null;
    }else
        return $output;
}