<?php
/**
 * 扎取益生康健
 */

echo '<pre>';

$url = "http://yeecare.tmall.com/category-884642718.htm?spm=a1z10.5.w4010-6232518504.8.qP6g84&search=y&parentCatId=884642717&parentCatName=%F7%C8%C1%A6%C4%D0%C8%CB&catName=%B8%C4%C9%C6%CA%D3%C1%A6#bd";
$str = HttpGet($url);
 
$str=preg_replace("/\s+/", " ", $str); //过滤多余回车 
$str=preg_replace("/<[ ]+/si","<",$str); //过滤<__("<"号后面带空格) 
$str=preg_replace("/<\!--.*?-->/si","",$str); //注释 

// print_r($str);die;

preg_match_all('/<li class="cat fst-cat\s?">\s?<h4.+>(.+)<\/h4>\s?<ul.+>(.+)<\/ul>\s?<\/li>/isU',$str,$lists);
unset($lists[1][0]);
unset($lists[2][0]);
$temp[] = $lists[1];
$temp[] = $lists[2];
// var_dump($temp);die; 
$category_1 = array();
foreach ($temp[0] as $k => $v) {
	// var_dump($v);die;
	preg_match_all('/<a.+href=\"(.*?)\".*?>(.*?)<\/a>/i', $v, $category_1[]);
	// var_dump($category_1);die;
}
// var_dump($category_1);
foreach ($temp[1] as $k => $v) {
	// var_dump($v);
	preg_match_all('/<a.*?href=\"(.*?)\".*?>(.*?)<\/a>/i', $v, $temp_1);
	for($i = 0; $i < count($temp_1[1]); $i++){
		$category_2[($k-1)][] = array(
			'name' => $temp_1[2][$i],
			'link' => $temp_1[1][$i]
			);
	}
}
// var_dump($category_2);
// var_dump($category_1);die;
foreach ($category_1 as $k => $v) {
	$category[$k] = array(
		'fname' => $v[2][0],
		'flink' => $v[1][0],
		'children' => $category_2[$k],
	);
}
// var_dump($category);die;

foreach ($category as $k => $v) {
	for ($i=0; $i < count($v['children']); $i++) { 
		$url = $v['children'][$i]['link'];
		$str = HttpGet($url);
// print_r($url);
		$str=preg_replace("/\s+/", " ", $str); //过滤多余回车 
		$str=preg_replace("/<[ ]+/si","<",$str); //过滤<__("<"号后面带空格) 
		$str=preg_replace("/<\!--.*?-->/si","",$str); //注释 		

		preg_match_all('/<span class=\"c-price\">(\d+.\d+)/', $str, $price);//获取价格
		preg_match_all('/<a class="item-name".*?>(.*?)<\/a>/', $str, $name);//名称
		preg_match_all('/<span class="sale-num">(\d+)<\/span>/', $str, $sale_num);//销量
		preg_match_all('/<a\sclass="item-name".*?href=\"http:\/\/detail.tmall.com\/item.htm\?id=(\d+)&/', $str, $skuId);//商品ID
		preg_match_all('/<a\sclass="item-name".*?href=\"(.*?)\".*?>.*?<\/a>/', $str, $url);//url

		$data = array();
		for ($j=0; $j < count($name[1]); $j++) { 
			$data[] = array(
				'name' => $name[1][$j],
				'price' => $price[1][$j],
				'skuId' => $skuId[1][$j],
				'url' => $url[1][$j],
				'category_1' => $category[$k]['fname'],
				'category_2' => $category[$k]['children'][$i]['name'],
				);	
		}
		// var_dump($data);die;

		insert_db($data);


//		die;
	}
}



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
        $sql = 'INSERT INTO yisheng (name, price, skuId, url, category_1, category_2) VALUES ("'.characet($v['name']).'", "'.$v['price'].'", "'.$v['skuId'].'", "'.$v['url'].'", "'.characet($v['category_1']).'", "'.characet($v['category_2']).'")';
        $res = $con->query($sql);

		//echo iconv("UTF-8","gb2312",$sql)."\n";
        
		if(!$res){
            echo "插入失败！商品地址：".$v[1]."\n"; 
            echo iconv("UTF-8","gb2312",$sql)."\n";
        }else{
			echo ".";
            $insert_num++;
        }
    }
	echo "\n";
	echo "成功写入".$insert_num."条数据\n";
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
    $timeout = 10;  
    curl_setopt($ch, CURLOPT_URL, $url);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  //返回结果
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); //设置超时时间
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //允许重定向
    curl_setopt($ch, CURLOPT_HEADER, 0);//是否输出头信息 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //禁用SSL
    $file_contents = curl_exec($ch);  
    echo curl_error($ch);
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