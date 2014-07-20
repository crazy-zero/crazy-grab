<?php
//$method = 'jingdong.ware.baseproduct.get';
//$param = '{"ids": 1180338468, "base": "name,state,brand_name,value_weight,weight,product_area,sale_date"}';
//$data = get_data($method, $param, $use_jd_num);
//print_r($data);die;
	/**
	 * 扎取京东营养保健品类的团单
	 */
    header("Content-Type: text/html;charset=utf-8");
    if(date_default_timezone_get() != "1Asia/Shanghai") date_default_timezone_set("Asia/Shanghai");
echo "<pre>";
    /**
     * 指定需要调用的API
     */
    $method = "jingdong.ware.product.catelogy.list.get";//获取商品类目信息接口
    // $method = "jingdong.ware.promotion.search.catelogy.list";//根据三级类目编号查询商品列表
    
    $cid = 9192;//京东保健品一级分类的ID

    /**
     * 指定 API 需要传入的应用级参数
     */
    $param = '{"catelogyId":"' . $cid . '","level":"0","isIcon":"true","isDescription":"true","client":"m"}';//查看类目应用级参数
    // $param = '{"catelogyId":"9209","page":"6","pageSize":"100","client":"m"}';//根据三级类目编号查询商品列表的应用级参数
 
    
	$use_jd_num = 0;

    //开始获取分类数据
    $data = get_data($method, $param, $use_jd_num);

    //获取所有最低级分类ID
    $cids = array();//所有三级分类ID
    $res = array();//二级分类的子分类
	$category = array();
    foreach ($data["jingdong_ware_product_catelogy_list_get_responce"]["productCatelogyList"]["catelogyList"] as $k => $v) {
        $param = '{"catelogyId":"' . $v['cid'] . '","level":"0","isIcon":"true","isDescription":"true","client":"m"}';
        $res[$v['cid']] = $two = get_data($method, $param, $use_jd_num);
        foreach ($two["jingdong_ware_product_catelogy_list_get_responce"]["productCatelogyList"]["catelogyList"] as $value) {
            $cids[] = $value['cid'];
			$category[$value['cid']] = array(
				'name_2' => $v['name'],
				'name_3' => $value['name'],
			);
        }
    }
    //开始获取商品数据    
	$use_api_num = 0;//记录调用京东API的数量
    $pages = array();//一页商品的信息
    $page = 0;//页码
    $infos = 0;//商品信息
	$skuId_arr = select_db();//读取所有已抓取的skuId
    for ($i=59; $i < count($cids); $i++) { 
		$page = 0;
		do{
			$page++;
			$method = "jingdong.ware.promotion.search.catelogy.list";//根据三级类目编号查询商品列表
			$param = '{"catelogyId":"' . $cids[$i] . '","page":"' . $page . '","pageSize":"100","client":"m"}';//根据三级类目编号查询商品列表的应用级参数   

			echo "\n正在获取CategoryId:".$cids[$i].'的第'.$page.'页(分页数100)'."\n";
			//读取服务端数据
			$pages[] = $temp = get_data($method, $param, $use_jd_num);

			if(empty($temp)){
				echo '失败！获取CategoryId:'.$cids[$i].'的第'.$page.'页(分页数100)'."\n";
			}else{
				echo '成功！获取CategoryId:'.$cids[$i].'的第'.$page.'页(分页数100)'."\n";
			}
			if(empty($temp["jingdong_ware_promotion_search_catelogy_list_responce"]["searchCatelogyList"]["wareCount"])){
				echo "don't hava data\n";
				echo "$i = ".$i." $cids[$i] = ".$cids[$i];
				echo " $page = ".$page."\n";
				break;
			}
			$wareCount = $temp["jingdong_ware_promotion_search_catelogy_list_responce"]["searchCatelogyList"]["wareCount"];//商品总数量
			
			

			echo 'CategoryId:'.$cids[$i].'共有'.$wareCount."条信息\n";

			//处理获取的当前页的数据
			$data = $temp['jingdong_ware_promotion_search_catelogy_list_responce']['searchCatelogyList']['wareInfo'];

			//获取商品详细信息
			foreach ($data as $key => $value) {
				echo "\nGeting $i = ".$i." CategoryName:".iconv("UTF-8", "GB2312//IGNORE",$category[$cids[$i]]['name_3'])." Page:".$page." skuId:".$value['skuId']." info\n";

				if(in_array($value['skuId'], $skuId_arr)){
					echo "db have the skuId jump over\n";
					continue;
				}

				//获取 商品上架时间
				$url = "http://item.jd.com/".$value['skuId'].".html";//单个商品页地址
				$str = HttpGet($url);
				$str = characet($str);
				$str=preg_replace("/\s+/", " ", $str); //过滤多余回车 
				$str=preg_replace("/<[ ]+/si","<",$str); //过滤<__("<"号后面带空格) 
				$str=preg_replace("/<\!--.*?-->/si","",$str); //注释             
				preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $str, $sale_time_arr);			
				if(empty($sale_time_arr[1])){
					$sale_tiem = '';
					print_r("skuId:".$value['skuId']." don't have 'sale_tiem'\n");
//					print_r($sale_time_arr);
//					die;
				}else{
					$sale_time = $sale_time_arr[1];
				}

				//获取 商品评价数量
				$url = "http://club.jd.com/ProductPageService.aspx?method=GetCommentSummaryBySkuId&referenceId=".$value['skuId']."&callback=getCommentCount";
				$str = HttpGet($url);
				preg_match('/CommentCount":(\d.*?),/', $str, $good_num_arr);
				$good_num = $good_num_arr[1];

				//获取 广告语 商品评分 京东价 市场价
				$method = 'jingdong.ware.product.detail.search.list.get';
				$param = '{"skuId": '.$value['skuId'].', "isLoadWareScore": true, "client": "m"}';
				$data = get_data($method, $param, $use_jd_num);
				$productInfo = $data['jingdong_ware_product_detail_search_list_get_responce']['productDetailList']['productInfo'];

				if(empty($productInfo['adword'])){
					$adword = '';
					print_r("skuId: ".$value['skuId']." don't have 'adword'\n");
	//				print_r($productInfo);
	//				 die;
				}else{
					 $adword = $productInfo['adword'];
				}
				$good = $productInfo['good'];
				$jdPrice = $productInfo['jdPrice'];
				$marketPrice = $productInfo['marketPrice'];

				//获取 品牌 商品名称 商品毛重 商品产地
				$method = 'jingdong.ware.baseproduct.get';
				$param = '{"ids": '.$value['skuId'].', "base": "name,state,brand_name,value_weight,weight,product_area,sale_date"}';
				$data = get_data($method, $param, $use_jd_num);
				$no_skuId = array(1177695910, 1180335276, 1180335276,1180346079,1180343182,1166666618,1166657621,1020838414,1080355457,1029640960,1020835340,1027360303,1073543590);
				if(empty($data['jingdong_ware_baseproduct_get_responce']['product_base']) && !in_array($value['skuId'], $no_skuId)){
					die('api num use end\n');
				}
				$product_base = $data['jingdong_ware_baseproduct_get_responce']['product_base'];
				$brand_name = $product_base[0]['brand_name'];
				$name = $product_base[0]['name'];
				$value_weight = $product_base[0]['value_weight'];
				if(empty($product_base[0]['product_area'])){
					$product_area = '';
					print_r("skuId: ".$value['skuId']."dont't have 'product_area'\n");
	//				print_r($product_base);
	//				die;
				}else{
					$product_area = $product_base[0]['product_area'];
				}

				//组装数组
				$datas = array(
					'skuId' => $value['skuId'],
					'name' => $name,
					'adword' => $adword,
					'jdPrice' => $jdPrice,
					'marketPrice' => $marketPrice,
					'good' => $good,
					'good_num' => $good_num,
					'url' => 'http://item.jd.com/'.$value['skuId'].'.html',
					'category_1' => characet('营养保健'),
					'category_2' => $category[$cids[$i]]['name_2'],
					'category_3' => $category[$cids[$i]]['name_3'],
					'brand_name' => $brand_name,
					'sale_time' => $sale_time,
					'value_weight' => $value_weight,
					'product_area' => $product_area,
					);
				//插入数据库
				insert_db($datas);
			}
		$panduan = $wareCount - $page * 100;
		echo "$panduan = ".$panduan."\n";
		} while($panduan >= 0);

        
    }

	function select_db(){
        $con = new mysqli("localhost","root","", "collection");
        if (!$con){
            die('连接数据库失败:' . $con->error() . "\n\n");
        }
        $con->query('SET NAMES UTF8');
		
		$sql = "SELECT skuId FROM jd GROUP BY skuId";

		$res = $con->query($sql);
		while($temp_row = $res->fetch_row()){
			$row[] = $temp_row[0];
		}
		return $row;
	}

    /**
     * 插入数据库
     * @param  [type] $data [description]
	 * @param  [type] $category 二级和三级分类名称
     * @return [type]       [description]
     */
    function insert_db($data) {
        $con = new mysqli("localhost","root","", "collection");
        if (!$con){
            die('连接数据库失败:' . $con->error() . "\n\n");
        }
        $con->query('SET NAMES UTF8');
		
		$sql = 'INSERT INTO jd (skuId, name, adword, jdPrice, marketPrice, good, good_num, url, category_1, category_2, category_3, brand_name, sale_time, value_weight, product_area) VALUES ("'.$data["skuId"].'", "'.$data['name'].'", "'.$data['adword'].'", "'.$data['jdPrice'].'", "'.$data['marketPrice'].'", "'.$data['good'].'", "'.$data['good_num'].'", "'.$data['url'].'", "'.$data['category_1'].'", "'.$data['category_2'].'", "'.$data['category_3'].'", "'.$data['brand_name'].'", "'.$data['sale_time'].'", "'.$data['value_weight'].'", "'.$data['product_area'].'")';
		
		$res = $con->query($sql);
		if($res){
			echo "success insert db\n"; 
		}else{
			echo 'fail insert db\n';
		}

        $con->close();
    }
    


    /**
     * 获取信息
     * @param  [type] $method [description]
     * @param  [type] $param  [description]
     * @return [type]         [description]
     */
    function get_data($method, $param, &$use_jd_num) {
        /**
         * 指定环境入口地址serverUrl，appKey,appSecretKey,token
         */
        $serverUrl = "http://gw.api.360buy.com/routerjson";//正式地址
//		$serverUrl = "http://gw.api.sandbox.360buy.com/routerjson";//沙箱地址
//      $appKey = "463A2AC702BA81B4BE2F68A19BF4E1C3";//xf9
//		$appSecretKey = "cf660ee61b644d67873070262ec9fd58";//xf9
//		$appKey = "8B9F33567751BC370AB93199892983EE";//2xf9
//      $appSecretKey = "c00a719ce26841dea7643008485665ae";//2xf9
//		$appKey = "702DC620FA0B096C9043C67CF9470B31";//3xf9
//		$appSecretKey = "9a41f7fe457443db843ac956721c5118";//3xf9
//		$appKey = "7DDF30C4393B2ADEBCD6BD47E917DB15";//4xf9
//		$appSecretKey = "fe73841c2d6148e9a1b46fd312169be5";//4xf9
//		$appKey = "1C5B25BDABAA4D91BF23A7930D179618";//5xf9
//		$appSecretKey = "56d189becdc1445eaaee1b5e03dd76ad";//5xf9
//		$appKey = "C821E534155050EBC1F41C3F5C69333E";//6xf9
//		$appSecretKey = "e0265f68e6fd4557819538f33e898882";//6xf9
//		$appKey = "7D7C761C5E3E20E15C0DA942438D7550";//7xf9
//		$appSecretKey = "63ca8a708ccf460da3f2244e8b5ca362";//7xf9
		$appKey = "E8BA95FCD02322AC07F056DD8FF9CBC9";//8xf9
		$appSecretKey = "250d5b233e1e46c7ac04b4977bb6b2f7";//8xf9
//		$appKey = "890E8D245D285B2AE98C635C2A32D31D";//9xf9
//		$appSecretKey = "848e4cb06bfd49b2952d6de711ffe572";//9xf9
//		$appKey = "CB1161C4EEBB083B54256CCF16A6B6C1";//10
//		$appSecretKey = "86712e7207a646a491b8ad02839eff88";//10
//		$appKey = "D35197181D2531297FB8A26DB1EA9C45";//11
//		$appSecretKey = "3453df0d23854c569232afc28cceb405";//11
//		$appKey = "0DC8F3BD553CFA3D0C4DF6E3C6F60588";//12
//		$appSecretKey = "76965a6f84914c0fa544158f0f016727";//12
//		$appKey = "9A81573A55785F303BEAFB1D23395182";//13
//		$appSecretKey = "627dcf9db5674ae9af7604eb25814ad4";//13
//		$appKey = "2AF22C294FE6FC922C9D5E82E86B31FD";//14
//		$appSecretKey = "7e9186b5f0734b5eaf1f4b815dd92fa5";//14
        $token="f0430d61-c2de-4310-8d71-c59a530a87f1";

        /**
         * 第一步：拼接原串，通过原串获取签名sign
         */
        //拼接原串
        $sourceSign = getParam($param, $token, $appKey, $method, $appSecretKey);
        //获得签名
        $sign = getSignByAPI($sourceSign);
        
        /**
         * 第二步：拼接系统参数
         */
        //拼接token
        $tokenUrl = isset($token)==false?"":("access_token=".$token);
        //请求的参数
        $data= "app_key=".$appKey."&"."v=2.0&".$tokenUrl."&"."method=".$method."&"."360buy_param_json=".urlencode($param)."&"."sign=".$sign."&timestamp=".urlencode(date("Y-m-d H:i:s",time()));

        /**
         * 第三步：发送请求，返回的数据即为响应的数据
         */
        
        //获取返回信息
        $response = HttpPost($serverUrl, $data);
echo "use jd api num : ".$use_jd_num++."\n";
        //json转数组后返回
        return json_decode($response, true);
    }
    
    //打印
    // var_dump(json_decode($response,true));
    // var_dump(str_replace("{", "<br/>{", $response));
    
    /**
     * 拼接原串
     * @param $param
     * @param $token
     * @param $appKey
     * @param $method
     * @param $appSecretKey
     */
    function getParam($param,$token,$appKey,$method,$appSecretKey){
        $timestamp = date("Y-m-d H:i:s",time());
         //拼接字符串
        $sourceSign = $appSecretKey."360buy_param_json".$param."access_token".$token."app_key".$appKey."method".$method."timestamp".$timestamp."v2.0".$appSecretKey;
        return $sourceSign;
    }
    
    /**
     * 根据拼接的原串获取签名
     * @param $sourceSign
     */
    function getSignByAPI($sourceSign)
    {
        $md5 = md5($sourceSign,false); //加密后的32位
        return strtoupper($md5);
    }

    /**
     * 发送请求并获取返回的信息
     * @param $url
     */
    function HttpGet($url)
    {   
        $ch = curl_init();  
        $timeout = 60;  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  //返回结果
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); //设置超时时间
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); //允许重定向
        curl_setopt($ch, CURLOPT_HEADER, 0);//是否输出头信息 
        curl_setopt($ch, CURLOPT_REFERER, 'http://item.jd.com/');
        // curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');//解决页面的gzip压缩
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //禁用SSL
        $file_contents = curl_exec($ch);  
        curl_close($ch);  
        return $file_contents; 
    }

    /**
     * 发送请求并获取返回的信息
     * @param $url
     * @param $param
     */
    function HttpPost($url,$param)
    {
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);//设置链接
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//设置是否返回信息
        curl_setopt($ch, CURLOPT_POST, 1);//设置为POST方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);//POST数据
        $response = curl_exec($ch);//接收返回信息
        if(curl_errno($ch)){//出错则显示错误信息
            print curl_error($ch);
        }
        curl_close($ch); //关闭curl链接
        return $response;//显示返回信息
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
          $data = mb_convert_encoding($data ,'utf-8' ,$fileType);
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
?>