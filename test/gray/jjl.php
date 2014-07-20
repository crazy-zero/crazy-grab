<?php
/**
 * 扎取家家乐购
 */
header("Content-Type: text/html;charset=utf-8");
echo "<pre>";

$url = 'http://www.jjlg.com.cn/';
$baseurl = 'http://www.jjlg.com.cn/';

$str = HttpGet($url);

$str=preg_replace("/\s+/", " ", $str); //过滤多余回车
$str=preg_replace("/<[ ]+/si","<",$str); //过滤“<”后面的空格
$str=preg_replace("/<\!--.*?-->/si","",$str); //过滤注释

preg_match_all('/<li>\s<div class="zx">(.*?)<\/div>\s<\/li>/', $str, $lists);

$category = array();//分类
foreach ($lists[1] as $k => $v) {
	// print_r($v);die;
	preg_match_all('/<h3><a.*?>(.*?)<\/a><\/h3>/', $v, $category_1);//获取主标题
	preg_match_all('/<dt>(.*?)<\/dt>/', $v, $category_2);//获取副标题
	preg_match_all('/<dd>(.*?)<\/dd>/', $v, $temp);//获取副标题题下的分类html代码
	if(!empty($temp[1][0])){
		preg_match_all('/<a href=\"(.*?)\".*?>(.*?)<\/a>/', $temp[1][0], $temp_1);//获取第一个副标题的下一级分类名和url
		
		for($i = 0; $i < count($temp_1[1]); $i++) {
			$category[] = array(
				'category_1' => $category_1[1][0],
				'category_2' => $category_2[1][0],
				'category_3' => $temp_1[2][$i],
				'link' => $baseurl.$temp_1[1][$i],
				);
		}
	}
	if(!empty($temp_2)){
		preg_match_all('/<a href=\"(.*?)\".*?>(.*?)<\/a>/', $temp[1][1], $temp_2);//获取第二个副标题的下一级分类名和url
		for($i = 0; $i < count($temp_2[1]); $i++) {
			$category[] = array(
				'category_1' => $category_1[1][0],
				'category_2' => $category_2[1][0],
				'category_3' => $temp_2[2][$i],
				'link' => $baseurl.$temp_2[1][$i],
				);
		}
	}
}

foreach ($category as $k => $v) {
	$url = $v['link'];

	do{
		echo "\ncategory page url：".$url."\n";

		$str = HttpGet($url);

		$str=preg_replace("/\s+/", " ", $str); //过滤多余回车
		$str=preg_replace("/<[ ]+/si","<",$str); //过滤“<”后面的空格
		$str=preg_replace("/<\!--.*?-->/si","",$str); //过滤注释

		preg_match_all('/<dl class="sProInfo clearfix">(.*?)<\/dl>/', $str, $temp_lists);//获取商品列表html

		if(!empty($temp_lists[1][0])){

			$goods_lists = array();//商品信息

			foreach ($temp_lists[1] as $key => $value) {
				preg_match_all('/<a href="(.*?)id=(\d+).*?".*?title="(.*?)".*?sProTip.*?>(.*?)<\/div>.*?sDetiCom">(.*?)<\/div>.*?(\d+.\d+)/', $value, $temp_info);//获取商品所需属性
				if(!empty($temp_info[5][0])){

					preg_match_all('/<span>(.*?)<\/span>/', $temp_info[5][0], $temp_yy_desc);
					if(empty($temp_yy_desc[1][0])){
						echo "temp_yy_desc 为空 跳过\n";
						$yy_desc = '';
					}else{
						$yy_desc = $temp_yy_desc[1][0];
					}
					
				}else{
					echo "temp_info 为空 跳过\n";
					$yy_desc = '';
				}


				if(empty($temp_info[2][0])){
					print_r($value);
					echo "\n";
					print_r($temp_info);
					echo "\n";
					die('读取数据出错');
				}
				$goods_lists[] = array(
					'skuId' => $temp_info[2][0],
					'name' => $temp_info[3][0],
					'url' => $baseurl.$temp_info[1][0].'id='.$temp_info[2][0],
					'tip' => $temp_info[4][0],
					'yy_desc' => $yy_desc,
					'price' => $temp_info[6][0],
					'category_1' => $category[$k]['category_1'],
					'category_2' => $category[$k]['category_2'],
					'category_3' => $category[$k]['category_3'],
					);
			}
		}else{
			echo "the page don't get data\n";
			continue;
		}

		preg_match_all('/<div class="sgoNext">\s?<a href="(.*?)".*?>/', $str, $next_url);//获取页码html

		if(!empty($next_url[1][0])){
			$url = $baseurl.$next_url[1][0];
			echo "next page url:".$url."\n";
		}else{
			echo "don't get next page url\n";
			unset($url);
		}

		//进入每一页读取商品详细信息
		foreach ($goods_lists as $good) {

			echo "good page url:".$good['url']."\n";

			$str = HttpGet($good['url']);

			$str=preg_replace("/\s+/", " ", $str); //过滤多余回车
			$str=preg_replace("/<[ ]+/si","<",$str); //过滤“<”后面的空格
			$str=preg_replace("/<\!--.*?-->/si","",$str); //过滤注释

			//开始匹配所需数据
			$match_arr = array();//存储正则匹配的数据
			preg_match('/<i class="gray09 fArial ml15">(.*?)<\/i>/', $str, $match_arr['skuId']);//商品编号
			if(empty($match_arr['skuId'][1])){
				die("don't get 'skuId' data\n");
			}
			preg_match('/<span id="goods_name_baike">(.*?)<\/span>/', $str, $match_arr['name']);//商品名称
			if(empty($match_arr['name'][1])){
				die("don't get 'name' data\n");
			}

			preg_match('/<em class="markt">(\d+.\d+)<\/em>/', $str, $match_arr['martPrice']);//市场价
			if(empty($match_arr['martPrice'][1])){
				$match_arr['martPrice'][1] = '';
				echo "don't get 'martPrice' data\n";
			}
			preg_match('/<i class="proScore">(.*?)<\/i>/', $str, $match_arr['good']);//评分
			if(empty($match_arr['good'][1])){
				$match_arr['good'][1] = '';
				echo "don't get 'good' data\n";
			}
			preg_match('/(\d+)条评论/', $str, $match_arr['good_num']);//评价人数
			if(empty($match_arr['good_num'][1])){
				$match_arr['good_num'][1] = '';
				echo "don't get 'good_num' data\n";
			}
			preg_match('/class="blue02 textDec">(.*?)</', $str, $match_arr['brand_name']);//品牌
			if(empty($match_arr['brand_name'][1])){
				$match_arr['brand_name'][1] = '';
				echo "don't get 'brand_name' data\n";
			}
			preg_match('/<i>生产厂家.*?>(.*?)<\/p/', $str, $match_arr['manufacturer']);//生产厂家
			if(empty($match_arr['manufacturer'][1])){
				$match_arr['manufacturer'][1] = '';
				echo "don't get 'manufacturer' data\n";
			}
			preg_match('/<i>规格.*?>(.*?)<\/p/', $str, $match_arr['specification']);//规格
			if(empty($match_arr['specification'][1])){
				$match_arr['specification'][1] = '';
				echo "don't get 'specification' data\n";
			}
			preg_match('/<i>食用方法.*?>(.*?)<\/p/', $str, $match_arr['methods_of_food']);//食用方法
			if(empty($match_arr['methods_of_food'][1])){
				$match_arr['methods_of_food'][1] = '';
				echo "don't get 'methods_of_food' data\n";
			}
			preg_match('/<i>成分含量.*?>(.*?)<\/p/', $str, $match_arr['ingredients']);//成分含量
			if(empty($match_arr['ingredients'][1])){
				$match_arr['ingredients'][1] = '';
				echo "don't get 'ingredients' data\n";
			}
			preg_match('/<i>主要成分.*?>(.*?)<\/p/', $str, $match_arr['main_ingredient']);//主要成分
			if(empty($match_arr['main_ingredient'][1])){
				$match_arr['main_ingredient'][1] = '';
				echo "don't get 'main_ingredient' data\n";
			}
			preg_match('/<i>有效期.*?>(.*?)<\/p/', $str, $match_arr['valid']);//有效期
			if(empty($match_arr['valid'][1])){
				$match_arr['valid'][1] = '';
				echo "don't get 'valid' data\n";
			}
			preg_match('/<i>储藏方法.*?>(.*?)<\/p/', $str, $match_arr['storage']);//储藏方法
			if(empty($match_arr['storage'][1])){
				$match_arr['storage'][1] = '';
				echo "don't get 'storage' data\n";
			}
			preg_match('/<i>注意事项.*?>(.*?)<\/p/', $str, $match_arr['precautions']);//注意事项
			if(empty($match_arr['precautions'][1])){
				$match_arr['precautions'][1] = '';
				echo "don't get 'precautions' data\n";
			}
			preg_match('/<i>禁忌人群.*?>(.*?)<\/p/', $str, $match_arr['taboo_crowd']);//禁忌人群
			if(empty($match_arr['taboo_crowd'][1])){
				$match_arr['taboo_crowd'][1] = '';
				echo "don't get 'taboo_crowd' data\n";
			}

			$data = array(
				'skuId' => $match_arr['skuId'][1],
				'name' => $match_arr['name'][1],
				'adword' => $good['tip'],
				'jdPrice' => $good['price'],
				'martPrice' => $match_arr['martPrice'][1],
				'good' => $match_arr['good'][1],
				'good_num' => $match_arr['good_num'][1],
				'url' => $good['url'],
				'category_1' => $v['category_1'],
				'category_2' => $v['category_2'],
				'category_3' => $v['category_3'],
				'brand_name' => $match_arr['brand_name'][1],
				'manufacturer' => $match_arr['manufacturer'][1],
				'specification' => $match_arr['specification'][1],
				'methods_of_food' => $match_arr['methods_of_food'][1],
				'ingredients' => $match_arr['ingredients'][1],
				'main_ingredient' => $match_arr['main_ingredient'][1],
				'valid' => $match_arr['valid'][1],
				'storage' => $match_arr['storage'][1],
				'precautions' => $match_arr['precautions'][1],
				'taboo_crowd' => $match_arr['taboo_crowd'][1],
				);

			//插入数据库
			insert_db($data);
			die;
		}	
	} while(!empty($url));
}




/**
 * 插入数据库
 * @param  [type] $data [description]
 * @return [type] $inser_num [返回成功插入的数量]
 */
function insert_db($data) {
    $con = new mysqli("localhost","root","", "collection");
    if (!$con){
        die('连接数据库失败:' . $con->error() . "\n\n");
    }
    $con->query('SET NAMES UTF8');
    $sql = "INSERT INTO jiajiale (skuId, name, adword, jdPrice, martPrice, good, good_num, url, category_1, category_2, category_3, brand_name, manufacturer, specification, methods_of_food, ingredients, main_ingredient, valid, storage, precautions, taboo_crowd) VALUES ({$data['skuId']}, {$data['name']}, {$dada['adword']}, {$data['jdPrice']}, {$data['martPrice']}, {$data['good']}, {$data['good_num']}, {$data['url']}, {$data['category_1']}, {$data['category_2']}, {$data['category_3']}, {$data['brand_name']}, {$data['manufacturer']}, {$data['specification']}, {$data['methods_of_food']}, {$data['ingredients']}, {$data['main_ingredient']}, {$data['valid']}, {$data['storage']}, {$data['precautions']}, {$data['taboo_crowd']})";
    $res = $con->query($sql);
    if($res){
        echo "success insert db\n";
    }else{
		echo iconv("UTF-8","gb2312",$sql)."\n";
        die("fail insert db\n");
    }
    $con->close();
    return ;
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
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/0 (Windows; U; Windows NT 0; zh-CN; rv:3)");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //允许重定向
    curl_setopt($ch, CURLOPT_HEADER, 0);//是否输出头信息 
	curl_setopt($ch, CURLOPT_REFERER, 'http://www.jjlg.com.cn/');
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