<?php

/*
 * +----------------------------------------------------------------------
 * | 1zg.com 杭州墨蜂网络科技有限公司
 * +----------------------------------------------------------------------
 * | Copyright (c) 2014 http://www.1zg.com All rights reserved.
 * +----------------------------------------------------------------------
 * | Author: dengzhengdu <dengzhengdu@1zg.com>
 * +----------------------------------------------------------------------
 * | 系统公共库文件,主要定义系统公共函数库
 */

/**
 * 检测用户是否登录
 * @return int 0-未登录，大于0-当前登录用户ID
 * @author xiaojun <liujun@1zg.com>
 */
function is_login($suffix) {
    $user = session('user_auth' . $suffix); //获取登录记录
    if (empty($user)) {
        return 0;
    } else {
        return $user;
        //return session('user_auth_sign') == data_auth_sign($user) ? $user['uid'] : 0;
    }
}

/**
 * 检测当前用户是否为管理员
 * @return boolean true-管理员，false-非管理员
 * @author xiaojun <liujun@1zg.com>
 */
function is_administrator($uid = null) {
    $uid = is_null($uid) ? is_login() : $uid;
    return $uid && (intval($uid) === C('USER_ADMINISTRATOR'));
}

/**
 * 设置跳转页面URL
 * 使用函数再次封装，方便以后选择不同的存储方式（目前使用cookie存储）
 * @author Xiao Jun <liujun@1zg.com>
 */
function set_redirect_url($url) {
    cookie('redirect_url', $url);
}

/**
 * 获取跳转页面URL
 * @return string 跳转页URL
 * @author Xiao Jun <liujun@1zg.com>
 */
function get_redirect_url() {
    $url = cookie('redirect_url');
    return empty($url) ? __APP__ : $url;
}

/**
 * 记录行为日志，并执行该行为的规则
 * @param string $action 行为标识
 * @param string $model 触发行为的表名（不加表前缀）
 * @param int $record_id 触发行为的记录id
 * @param int $user_id 执行行为的用户id
 * @return boolean
 * @author Xiao Jun <liujun@1zg.com>
 */
function action_log($action = null, $model = null, $record_id = null, $user_id = null) {

    //参数检查
    if (empty($action) || empty($model) || empty($record_id)) {
        return '参数不能为空';
    }
    if (empty($user_id)) {
        $user_id = is_login();
    }

    //查询行为,判断是否执行
    $action_info = M('Action')->getByName($action);
    if ($action_info['status'] != 1) {
        return '该行为被禁用';
    }

    //插入行为日志
    $data['action_id'] = $action_info['id'];
    $data['user_id'] = $user_id;
    $data['action_ip'] = ip2long(get_client_ip());
    $data['model'] = $model;
    $data['record_id'] = $record_id;
    $data['create_time'] = NOW_TIME;
    M('ActionLog')->add($data);

    //解析行为
    $rules = parse_action($action, $user_id);

    //执行行为
    $res = execute_action($rules, $action_info['id'], $user_id);
}

/**
 * 解析行为规则
 * 规则定义  table:$table|field:$field|condition:$condition|rule:$rule[|cycle:$cycle|max:$max][;......]
 * 规则字段解释：table->要操作的数据表，不需要加表前缀；
 *              field->要操作的字段；
 *              condition->操作的条件，目前支持字符串，默认变量{$self}为执行行为的用户
 *              rule->对字段进行的具体操作，目前支持四则混合运算，如：1+score*2/2-3
 *              cycle->执行周期，单位（小时），表示$cycle小时内最多执行$max次
 *              max->单个周期内的最大执行次数（$cycle和$max必须同时定义，否则无效）
 * 单个行为后可加 ； 连接其他规则
 * @param string $action 行为id或者name
 * @param int $self 替换规则里的变量为执行用户的id
 * @return boolean|array: false解析出错 ， 成功返回规则数组
 * @author Xiao Jun <liujun@1zg.com>
 */
function parse_action($action = null, $self) {
    if (empty($action)) {
        return false;
    }

    //参数支持id或者name
    if (is_numeric($action)) {
        $map = array('id' => $action);
    } else {
        $map = array('name' => $action);
    }

    //查询行为信息
    $info = M('Action')->where($map)->find();
    if (!$info || $info['status'] != 1) {
        return false;
    }

    //解析规则:table:$table|field:$field|condition:$condition|rule:$rule[|cycle:$cycle|max:$max][;......]
    $rules = $info['rule'];
    $rules = str_replace('{$self}', $self, $rules);
    $rules = explode(';', $rules);
    $return = array();
    foreach ($rules as $key => &$rule) {
        $rule = explode('|', $rule);
        foreach ($rule as $k => $fields) {
            $field = empty($fields) ? array() : explode(':', $fields);
            if (!empty($field)) {
                $return[$key][$field[0]] = $field[1];
            }
        }
        //cycle(检查周期)和max(周期内最大执行次数)必须同时存在，否则去掉这两个条件
        if (!array_key_exists('cycle', $return[$key]) || !array_key_exists('max', $return[$key])) {
            unset($return[$key]['cycle']);
            unset($return[$key]['max']);
        }
    }

    return $return;
}

/**
 * 执行行为
 * @param array $rules 解析后的规则数组
 * @param int $action_id 行为id
 * @param array $user_id 执行的用户id
 * @return boolean false 失败 ， true 成功
 * @author Xiao Jun <liujun@1zg.com>
 */
function execute_action($rules = false, $action_id = null, $user_id = null) {
    if (!$rules || empty($action_id) || empty($user_id)) {
        return false;
    }

    $return = true;
    foreach ($rules as $rule) {

        //检查执行周期
        $map = array('action_id' => $action_id, 'user_id' => $user_id);
        $map['create_time'] = array('gt', NOW_TIME - intval($rule['cycle']) * 3600);
        $exec_count = M('ActionLog')->where($map)->count();
        if ($exec_count > $rule['max']) {
            continue;
        }

        //执行数据库操作
        $Model = M(ucfirst($rule['table']));
        $field = $rule['field'];
        $res = $Model->where($rule['condition'])->setField($field, array('exp', $rule['rule']));

        if (!$res) {
            $return = false;
        }
    }
    return $return;
}

/**
 * 获取数据库中的配置列表
 * @return array 配置数组
 */
function get_config() {
    $map = array('status' => 1);
    $data = M('Config')->where($map)->field('type,name,value')->select();

    $config = array();
    if ($data && is_array($data)) {
        foreach ($data as $value) {
            $config[$value['name']] = parse_config($value['type'], $value['value']);
        }
    }
    return $config;
}

/**
 * 根据配置类型解析配置
 * @param  integer $type  配置类型
 * @param  string  $value 配置值
 */
function parse_config($type, $value) {
    switch ($type) {
        case 3: //解析数组
            $array = preg_split('/[,;\r\n]+/', trim($value, ",;\r\n"));
            if (strpos($value, ':')) {
                $value = array();
                foreach ($array as $val) {
                    list($k, $v) = explode(':', $val);
                    $value[$k] = $v;
                }
            } else {
                $value = $array;
            }
            break;
    }
    return $value;
}

/**
 * 分析枚举类型配置值 格式 a:名称1,b:名称2
 * @param type $string
 * @return type
 */
function parse_config_attr($string) {
    $array = preg_split('/[,;\r\n]+/', trim($string, ",;\r\n"));
    if (strpos($string, ':')) {
        $value = array();
        foreach ($array as $val) {
            list($k, $v) = explode(':', $val);
            $value[$k] = $v;
        }
    } else {
        $value = $array;
    }
    return $value;
}

/**
 * 获取配置的分组
 * @param string $group 配置分组
 * @return string
 */
function get_config_group($group = 0) {
    $list = C('CONFIG_GROUP_LIST');
    return $list[$group];
}

/**
 * 获取配置的类型
 * @param string $type 配置类型
 * @return string
 */
function get_config_type($type = 0) {
    $list = C('CONFIG_TYPE_LIST');
    return $list[$type];
}

/*
 * 权限认证
 * @param $rule 要验证的规则名称；
 * @param $uid 用户的id；
 * @param $relation 规则组合方式，默认为‘or’，以上三个参数都是根据Auth的check（）函数来的，
 */

function authcheck($rule, $uid, $relation = 'or', $rules = false) {
    //判断当前用户UID是否在定义的超级管理员参数里
    if ($uid == 1) {
        return array(true);
    } else {
        import('Common.Library.Auth'); //加载权限类库
        $auth = new \Auth();

        if ($rules) {
            return $auth->check($rule, $uid, $relation, true);
        } else {
            return $auth->check($rule, $uid, $relation);
        }
    }
}

/**
 * 系统邮件发送函数
 * @param string $to    接收邮件者邮箱
 * @param string $name  接收邮件者名称
 * @param string $subject 邮件主题 
 * @param string $body    邮件内容
 * @param string $attachment 附件列表
 * @return boolean 
 * @author xiaoxie <xiezhongwei@1zg.com>
 */
function send_mail($to, $name, $subject = '', $body = '', $attachment = null) {


    import("Common.Library.PHPMailer.PHPMailerAutoload", "", ".php"); //根据实际情况，导入PHPMailerAutoload.php类文件

    $mail = new \PHPMailer(); //PHPMailer对象
    $mail->CharSet = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->IsSMTP();  // 设定使用SMTP服务
    $mail->SMTPDebug = 0;                     // 关闭SMTP调试功能
    // 1 = errors and messages
    // 2 = messages only
    $mail->SMTPAuth = true;                  // 启用 SMTP 验证功能
    $mail->SMTPSecure = '';                 // 使用安全协议 
    $mail->Host = C('SMTP_HOST');  // SMTP 服务器
    $mail->Port = C('SMTP_PORT');  // SMTP服务器的端口号
    $mail->Username = C('SMTP_USER');  // SMTP服务器用户名
    $mail->Password = C('SMTP_PASS');  // SMTP服务器密码
    $mail->SetFrom(C('FROM_EMAIL'), C('FROM_NAME'));
    $replyEmail = C('REPLY_EMAIL') ? C('REPLY_EMAIL') : C('FROM_EMAIL');
    $replyName = C('REPLY_NAME') ? C('REPLY_NAME') : C('FROM_NAME');
    $mail->AddReplyTo($replyEmail, $replyName);
    $mail->Subject = $subject;
    $mail->MsgHTML($body);
    $mail->AddAddress($to, $name);
    if (is_array($attachment)) { // 添加附件
        foreach ($attachment as $file) {
            is_file($file) && $mail->AddAttachment($file);
        }
    }
    return $mail->Send() ? true : $mail->ErrorInfo;
}

/**
 * 把返回的数据集转换成Tree
 * @param array $list 要转换的数据集
 * @param string $pid parent标记字段
 * @param string $level level标记字段
 * @return array
 */
function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0) {
    // 创建Tree
    $tree = array();
    if (is_array($list)) {
        // 创建基于主键的数组引用
        $refer = array();
        foreach ($list as $key => $data) {
            $refer[$data[$pk]] = & $list[$key];
        }
        foreach ($list as $key => $data) {
            // 判断是否存在parent
            $parentId = $data[$pid];
            if ($root == $parentId) {
                $tree[] = & $list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent = & $refer[$parentId];
                    $parent[$child][] = & $list[$key];
                }
            }
        }
    }
    return $tree;
}

/**
 * 返回某个订单可执行的操作列表，包括权限判断
 * @param   array   $order      订单信息 order_status, shipping_status, pay_status
 * @param   bool    $is_cod     支付方式是否货到付款
 * @return  array   可执行的操作  confirm, pay, unpay, prepare, ship, unship, receive, cancel, invalid, return, drop
 * 格式 array('confirm' => true, 'pay' => true)
 */
function get_operable_list($order) {
    /* 取得订单状态、发货状态、付款状态 */
    $os = $order['order_status'];
    $ss = $order['shipping_status'];
    $ps = $order['pay_status'];
    /* 取得订单操作权限 */
//    $actions = $_SESSION['action_list'];
    $actions = 'all';
    if ($actions == 'all') {
        $priv_list = array('os' => true, 'ss' => true, 'ps' => true, 'edit' => true);
    } else {
        $actions = ',' . $actions . ',';
        $priv_list = array(
            'os' => strpos($actions, ',order_os_edit,') !== false,
            'ss' => strpos($actions, ',order_ss_edit,') !== false,
            'ps' => strpos($actions, ',order_ps_edit,') !== false,
            'edit' => strpos($actions, ',order_edit,') !== false
        );
    }

    /* 取得订单支付方式是否货到付款 */
//    $payment = payment_info($order['pay_id']);
    $is_cod = $payment['is_cod'] == 1;

    /* 根据状态返回可执行操作 */
    $list = array();
    if (OS_UNCONFIRMED == $os) {
        /* 状态：未确认 => 未付款、未发货 */
        if ($priv_list['os']) {
            $list['confirm'] = true; // 确认
            $list['invalid'] = true; // 无效
            $list['cancel'] = true; // 取消
            $list['split'] = true;
            if ($is_cod) {
                /* 货到付款 */
                if ($priv_list['ss']) {
                    $list['prepare'] = true; // 配货
                    $list['split'] = true; // 分单
                }
            } else {
                /* 不是货到付款 */
                if ($priv_list['ps']) {
                    $list['pay'] = true;  // 付款
                    $list['live'] = true;
                }
            }
        }
    } elseif (OS_CONFIRMED == $os || OS_SPLITED == $os || OS_SPLITING_PART == $os) {
        /* 状态：已确认 */
        if (PS_UNPAYED == $ps) {
            /* 状态：已确认、未付款 */
            if (SS_UNSHIPPED == $ss || SS_PREPARING == $ss) {
                /* 状态：已确认、未付款、未发货（或配货中） */
                if ($priv_list['os']) {
                    $list['cancel'] = true; // 取消
                    $list['invalid'] = true; // 无效
                }
                if ($is_cod) {
                    /* 货到付款 */
                    if ($priv_list['ss']) {
                        if (SS_UNSHIPPED == $ss) {
                            $list['prepare'] = true; // 配货
                        }
                        $list['split'] = true; // 分单
                    }
                } else {
                    /* 不是货到付款 */
                    if ($priv_list['ps']) {
                        $list['pay'] = true; // 付款
                        $list['live'] = true;
                    }
                }
            }
            /* 状态：已确认、未付款、发货中 */ elseif (SS_SHIPPED_ING == $ss || SS_SHIPPED_PART == $ss) {
                // 部分分单
                if (OS_SPLITING_PART == $os) {
                    $list['split'] = true; // 分单
                }
                $list['to_delivery'] = true; // 去发货
            } else {
                /* 状态：已确认、未付款、已发货或已收货 => 货到付款 */
                if ($priv_list['ps']) {
                    $list['pay'] = true; // 付款
                }
                if ($priv_list['ss']) {
                    if (SS_SHIPPED == $ss) {
                        $list['receive'] = true; // 收货确认
                    }
                    $list['unship'] = true; // 设为未发货
                    if ($priv_list['os']) {
                        $list['return'] = true; // 退货
                    }
                }
            }
        } else {
            /* 状态：已确认、已付款和付款中 */
            if (SS_UNSHIPPED == $ss || SS_PREPARING == $ss) {
                /* 状态：已确认、已付款和付款中、未发货（配货中） => 不是货到付款 */
                if ($priv_list['ss']) {
                    if (SS_UNSHIPPED == $ss) {
                        $list['prepare'] = true; // 配货
                    }
                    $list['split'] = true; // 分单
                }
                if ($priv_list['ps']) {
                    $list['unpay'] = true; // 设为未付款
                    if ($priv_list['os']) {
                        $list['cancel'] = true; // 取消
                    }
                }
            }
            /* 状态：已确认、未付款、发货中 */ elseif (SS_SHIPPED_ING == $ss || SS_SHIPPED_PART == $ss) {
                // 部分分单
                if (OS_SPLITING_PART == $os) {
                    $list['split'] = true; // 分单
                }
                $list['to_delivery'] = true; // 去发货
            } else {
                /* 状态：已确认、已付款和付款中、已发货或已收货 */
                if ($priv_list['ss']) {
                    if (SS_SHIPPED == $ss) {
                        $list['receive'] = true; // 收货确认
                    }
                    if (!$is_cod) {
                        $list['unship'] = true; // 设为未发货
                    }
                }
                if ($priv_list['ps'] && $is_cod) {
                    $list['unpay'] = true; // 设为未付款
                }
                if ($priv_list['os'] && $priv_list['ss'] && $priv_list['ps']) {
                    $list['return'] = true; // 退货（包括退款）
                }
            }
        }
    } elseif (OS_CANCELED == $os) {
        /* 状态：取消 */
        if ($priv_list['os']) {
            $list['confirm'] = true;
        }
        if ($priv_list['edit']) {
            $list['remove'] = true;
        }
    } elseif (OS_INVALID == $os) {
        /* 状态：无效 */
        if ($priv_list['os']) {
            $list['confirm'] = true;
        }
        if ($priv_list['edit']) {
            $list['remove'] = true;
        }
    } elseif (OS_RETURNED == $os) {
        /* 状态：退货 */
        if ($priv_list['os']) {
            $list['confirm'] = true;
        }
    }

    /* 修正发货操作 */
    if (!empty($list['split'])) {

        /* 如果部分发货 不允许 取消 订单 */
//        if (order_deliveryed($order['order_id'])) {
//            $list['return'] = true; // 退货（包括退款）
//            unset($list['cancel']); // 取消
//        }
    }

    /* 售后 */
    $list['after_service'] = true;

    return $list;
}

/**
 * 判断订单是否已发货（含部分发货）
 * @param   int     $order_id  订单 id
 * @return  int     1，已发货；0，未发货
 */
function order_deliveryed($order_id) {
    $return_res = 0;

    if (empty($order_id)) {
        return $return_res;
    }

    $sql = 'SELECT COUNT(delivery_id)
            FROM ' . $GLOBALS['ecs']->table('delivery_order') . '
            WHERE order_id = \'' . $order_id . '\'
            AND status = 0';
    $sum = $GLOBALS['db']->getOne($sql);

    if ($sum) {
        $return_res = 1;
    }

    return $return_res;
}

/**
 * 取得状态列表
 * @param   string  $type   类型：all | order | shipping | payment
 */
function get_status_list($type = 'all') {
    global $_LANG;

    $list = array();

    if ($type == 'all' || $type == 'order') {
        $pre = $type == 'all' ? 'os_' : '';
        foreach ($_LANG['os'] AS $key => $value) {
            $list[$pre . $key] = $value;
        }
    }

    if ($type == 'all' || $type == 'shipping') {
        $pre = $type == 'all' ? 'ss_' : '';
        foreach ($_LANG['ss'] AS $key => $value) {
            $list[$pre . $key] = $value;
        }
    }

    if ($type == 'all' || $type == 'payment') {
        $pre = $type == 'all' ? 'ps_' : '';
        foreach ($_LANG['ps'] AS $key => $value) {
            $list[$pre . $key] = $value;
        }
    }
    return $list;
}

/**
 * 文件上传配置
 * @author zhoulianlei<zhoulianlei@lzg.com>
 */
function uploadConfig() {
    return array(
        'maxSize' => 31457280,
        'saveName' => time() . '_' . mt_rand(),
        'exts' => array('jpg', 'png', 'jpeg'),
        'autoSub' => true,
        'subName' => array('date', 'Ymd'),
    );
}

/**
 * 上传一张图片
 * @param string $fileName  文件名称
 * @param array $extraConfig  额外配置数组
 * @return array 文件上传后的信息
 * @author zhoulianlei<zhoulianlei@lzg.com>
 */
function upload($fileName, $extraConfig = '') {

    $config = uploadConfig();
    if ($extraConfig) {
        $config = array_merge($config, $extraConfig);
    }
    $upload = new \Think\Upload($config);
    // 上传单个文件
//	$upload->autoSub = false; // 禁止生成冗余目录
    $info = $upload->uploadOne($_FILES[$fileName]);

    return $info;
}

/**
 * 
 * @param type $fileName
 * @param type $extraConfig
 * @return type
 */
function uploadfiles($extraConfig = '') {

    $config = uploadConfig();
    if ($extraConfig) {
        $config = array_merge($extraConfig, $config);
    }

    $upload = new \Think\Upload($config);
    // 上传单个文件
//	$upload->autoSub = false; // 禁止生成冗余目录
    $info = $upload->upload();
    return $info;
}

/**
 * 
 * @param string $fileName  文件名称
 * @param int $width   生成宽度
 * @param int $height   生成高度
 * @param string $path   存放路径
 * @param string $suffix  后最名
 * @author zhoulianlei<zhoulianlei@lzg.com>
 */
function thumb($fileName, $width, $height, $path, $suffix, $pattern) {
    $image = new \Think\Image();
    $image->open($path . $fileName);

    $fileName = explode('.', $fileName);
    $file = $fileName[0];
    $type = ($fileName[1] == 'jpg') ? 'jpg' : $image->type();
    $saveName = $path . $file . '_' . $suffix . '.' . $type;

    $image->thumb($width, $height, $pattern)->save($saveName);
}

/**
 * 取得状态列表
 * @param   string  $type   类型：all | order | shipping | payment
 */
function get_order_status($status, $type = '') {
    $config = array(
        'order' => array('未确认', '已确认', '已取消', '无效', '退货', '已完成', '现场支付', '配送中', '已删除', '待发货', '已发货'),
        'pay' => array('待付款', '退款中', '已付款', '退款完成'),
        'shipping' => array('未发货', '已发货', '已收货', '备货中', '已发货(部分商品)', '发货中(处理分单)', '已发货(全部)'),
        'all' => array(
            '100' => '待付款',
            '101' => '待发货',
            '102' => '已发货',
            '111' => '已完成',
        ),
    );

    $status_text = $config[$type][$status];
    return $status_text;
}

/**
 * 获取操作权限
 * $order_id int 订单id号
 * $type string 当前要执行的操作
 */
function operate_priv($order_id, $type) {
    $order_info = M('Order')->field('pay_status,order_status,shipping_status')->find($order_id);
    extract($order_info);
    switch ($type) {
        //发货
        case 'pay_ship':
            if ($pay_status == 2 && ($order_status == 9 || $order_status == 10)) {
                return true;
            } else {
                return '您没有发货权限';
            }

            break;
        case 'invalid':
            if ($pay_status == 0) {
                return true;
            } else {
                return '您没有无效操作权限';
            }
            break;
        default:
            break;
    }
}

/**
 * 获取缩略图
 */
function get_thumb($path, $suffix) {
    /*
      $pic = explode('.', $path);
      $pics = $pic[0] . "_$suffix";

      $pics.='.' . $pic[1];
      if (!file_exists('./Uploads/' . $pics)) {
      $pics = $path;
      }
      return $pics; */
//	$suffix = end(explode('.', basename($path)));
    //	$thumb = "@1e_{$px}w_{$px}h_0c_0i_1o_100Q_1x.".$suffix;
    return getAliyunThumb($path, $suffix);
}

/**
 * 计算购物车和收藏总数
 */
function get_car_num($uid, $redis) {
    $all_num['collect_num'] = get_collect($uid, $redis);
    $all_num['cart_num'] = get_cart($uid, $redis);
//    $all_num = M('User')->field('cart_num,collect_num')->find($uid);

    return $all_num;
}

/**
 * 得到购物车数量
 */
function get_cart($uid, $redis) {
    $all_num = $redis->get('cart_' . $uid);
    if (false === $all_num) {
    //    $where = "user_id=$uid";
        $where['user_id'] = $uid;
        $where['Cart.status'] = 1;
        $CartView = new \Home\Model\CommonViewModel();
        $CartView->setProperty('viewFields', \Home\Model\CommonViewModel::$cart);
        $all_num = $CartView->where($where)->sum('Cart.goods_number');
        $all_num = empty($all_num) ? 0 : $all_num;
        $redis->setex('cart_' . $uid, C('REDIS_CACHE_TIME'), $all_num);
    }


    return $all_num;
}

/**
 * 得到购物车总金额
 */
function get_cart_amount($uid) {
    $where['user_id'] = $uid;
    $where['Cart.status'] = 1;
    $CartView = new \Home\Model\CommonViewModel();
    $CartView->setProperty('viewFields', \Home\Model\CommonViewModel::$cart);
    $cart_info = $CartView->where($where)->select();
    $shipping_fee = array();
    foreach ($cart_info as $k => $v) {
        $shipping_fee[] = array(
            'supplier_id' => $v['supplier_id'],
            'shipping_fee' => $v['shipping_fee'],
        );
        $all_amount += $v['shop_price'] * $v['goods_number'];
    }
    $all_amount += total_shipping_fee($shipping_fee);
    return $all_amount;
}

/**
 * 得到收藏数量
 */
function get_collect($uid, $redis) {
    $all_num = $redis->get('collect_' . $uid);
    if (false === $all_num) {
        $where = "user_id=$uid AND status=1";
        $CollectView = M('Collect');
        //    $CollectView = new \Member\Model\CommonViewModel();
        //    $CollectView->setProperty('viewFields', \Member\Model\CommonViewModel::$collect);
        $all_num = $CollectView->where($where)->count();
        $all_num = empty($all_num) ? 0 : $all_num;
        $redis->setex('collect_' . $uid, C('REDIS_CACHE_TIME'), $all_num);
    }


    return $all_num;
}

/**
 * 前台会员操作限制
 */
function member_operate($message, $id) {

    $order_info = M('Order')->field('pay_status,order_status,shipping_status')->find($id);
    if ($order_info['pay_status'] == 2 && $order_info['order_status'] == 9) {
        $message = '您已经支付过,' . $message;
    } else if ($order_info['order_status'] == 5 && $order_info['pay_status'] == 2 && $order_info['shipping_status'] == 2) {
        $message = '该商品已发货,' . $message;
    } else if ($order_info['order_status'] == 3) {
        $message = '该订单已失效,' . $message;
    } else if ($order_info['order_status'] == 2 && $order_info['pay_status'] == 0) {
        $message = '该订单已取消,' . $message;
    } else {
        $message = true;
    }

    return $message;
}

/**
 * get_hash_value 
 * 
 * 获取redis哈希表中的一个值
 *
 * 不存在返回空
 *
 * 返回值 string 型
 *
 */
function get_hash_value($redis, $key, $field) {
    $goods_number = $redis->hget($key, $field);
    return $goods_number;
}

/**
 * set_hash_value 
 * 设置哈希表的值
 *
 */
function set_hash_value($redis, $key, $field, $number) {
    $goods_number = $redis->hincrby($key, $field, $number);
    return $goods_number;
}

/**
 * add_hash_value 
 * 新增hash表的数值
 * 存在相同的则覆盖
 */
function add_hash_value($redis, $key, $field, $number) {
    $goods_number = $redis->hset($key, $field, $number);
    return $goods_number;
}

/**
 * upload_cloud 
 * 上传到云 公共调用函数
 * @param mixed $path 
 * @access public
 * @return void
 */
function uploadCloud($filePath) {
    $aliyun = new \Common\Library\AliyunUpload();
    $url = $aliyun->putFile($filePath);
//		unlink($filePath);
    return $url;
}

/**
 * getAliyunThumb 
 *
 * 获取云端url 返回云端的缩略图路径
 *
 * @url 云端原始图片的路径
 *
 * @px 云端的缩略图尺寸
 *
 * demo: 
 *
 * http://pinke52.oss-cn-hangzhou.aliyuncs.com/5327aac2b3b1d.jpg
 *
 * return demo: 
 *
 * http://pinke52.img-cn-hangzhou.aliyuncs.com/5327a9986e9fa.jpg@1e_75w_75h_0c_0i_1o_100Q_1x.jpg
 * 
 * @access public
 *
 * @return $url 
 */
function getAliyunThumb($url, $px) {
    $img_host = C('OSS_ALIYUN_IMG_DISPOSE_DOMAIN');
    $key = basename($url);
    $suffix = end(explode('.', $key));
    $thumb = 'http://' . $img_host . '/' . $key . "@1e_{$px}w_{$px}h_0c_0i_1o_100Q_1x." . $suffix;
    return $thumb;
}

/**
 * create_url 
 * 封装云url  暂时废弃
 * @param mixed $key 
 * @access public
 * @return void
 */
function create_url($key) {
    $host = C('OSS_ALIYUN_DOMAIN');
    return $host . '/' . $key;
}

/**
 * create_name 
 * 创建更好的随机名称 随机种子 + 微妙 + 后缀 
 * @param mixed $suffix 
 * @access public
 * @return void
 */
function create_name($suffix) {
    return mt_rand() . '_' . uniqid($suffix);
}

/**
 * 计算一个订单总的物流费用  同一个供应商支付一份物流费
 * @param array $info
 */
function total_shipping_fee($info) {
    $fee_info = array();
    foreach ($info as $k => $v) {
        if ($fee_info) {
            $supplier_id = array_column($fee_info, 'supplier_id');
            if (in_array($v['supplier_id'], $supplier_id)) {
                $fee_info[$v['supplier_id']]['shipping_fee'] = max($v['shipping_fee'], $fee_info[$v['supplier_id']]['shipping_fee']);
            } else {
                $fee_info[$v['supplier_id']] = array(
                    'supplier_id' => $v['supplier_id'],
                    'shipping_fee' => $v['shipping_fee'],
                );
            }
        } else {
            $fee_info[$v['supplier_id']] = array(
                'supplier_id' => $v['supplier_id'],
                'shipping_fee' => $v['shipping_fee'],
            );
        }
    }
    $shipping_fees = array_column($fee_info, 'shipping_fee');
    return array_sum($shipping_fees);
}

/**
 * get_table_name 
 * 根据不同的组ID 获取不同的表名
 * @param mixed $gid 
 * @access public
 * @return void
 */
function get_table_name($gid) {
    switch ($gid) {
        case '1':
            $view = 'staff';
            break;
        case '2':
            $view = 'supplier';
            break;
        case '3':
            $view = 'member';
            break;
        default:
            $view = 'member';  //默认为员工组
            break;
    }
    return $view;
}

/**
 *  stdClass Object => array
 *  递归处理
 *  json 对象转换为标准的数组
 */
function object_array($array) {
    if (is_object($array)) {
        $array = (array) $array;
    }
    if (is_array($array)) {
        foreach ($array as $key => $value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

/*
  function query($object,$sql){

  } */

/**
 * 得到子订单的操作列表
 */
function get_son_action_list($id) {
    $action_list = M('SupplierAction')->order('id asc')->where("order_id=$id")->select();
    return $action_list;
}

/**
 * 根据当前供应商  获取所在省市的所有供应商
 */
function get_all_supplier($supplier_id,$is_admin=false) {

     $super_supplier_id = C('SUPPER_SUPPLIER');
    $province = get_province($supplier_id);
    $notin_uids = "1";
    if(!$is_admin){
        $notin_uids .=",$super_supplier_id";
    }
    $where = array(
        'province' => $province,
        'uid' => array('not in',$notin_uids),
    );
    $supplier_list = D('UserSupplier')->field('uid,company')->where($where)->select();
    return $supplier_list;
}

/**
 * 根据供应商 获取省份
 */
function get_province($supplier_id) {
    $province = D('UserSupplier')->field('province')->find($supplier_id);
    $province = $province['province'];
    return $province;
}

/**
 * 同一个供应商只显示一个运费
 */
function show_shipping_fee($goods_info) {
    $supplier_goods = array();
    foreach ($goods_info as $k => $v) {
        $supplier_goods[$v['supplier_id']] = max($v['shipping_fee'], $supplier_goods[$v['supplier_id']]['shipping_fee']);
    }
    foreach ($supplier_goods as $k => $v) {
        $i = 0;
        foreach ($goods_info as $k2 => $v2) {
            if ($i == 0 && ($v2['supplier_id'] == $k)) {
                $goods_info[$k2]['shipping_fee'] = $v;
                $i++;
            } else if ($v2['supplier_id'] == $k) {
                $goods_info[$k2]['shipping_fee'] = -1;
            }
        }
    }
    return $goods_info;
}

/**
 * 获取供应商的公司名称
 */
function get_supplier($supplier_id) {
    $supplier_info = D('UserSupplier')->field('company')->find($supplier_id);
    return $supplier_info['company'];
}

/**
 * 订单支付完成后  有效子订单全部置位已支付订单
 */

/**
 * 调取物流信息
 */
function get_shipping_info($order_id) {
    $shipping_info = D('ExpressInfo')->field('data_info')->where('order_id=' . $order_id)->find();
    $data = object_array(json_decode($shipping_info['data_info']));
    return $data;
}

/**
 * 判断是否是员工组
 */
function is_staff_member($uid = '', $gid = '') {
    $staff_group_id = C('STAFF_GROUP_ID');
    $staff_group_id = $staff_group_id ? $staff_group_id : 1;
    if (empty($uid) && empty($gid)) {
        //都为空  则取session的值
        $staff_info = session('user_auth_home');
        $own_staff_group_id = $staff_info['gid'];
    } else if ($uid && empty($gid)) {
        $gids = M('AuthGroupAccess')->find($uid);
        $own_staff_group_id = $gids['group_id'];
    } else if ($gid) {
        $own_staff_group_id = $gid;
    }

    if ($own_staff_group_id == $staff_group_id) {
        return true;
    } else {
        return false;
    }
}

function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true) {
    if (function_exists("mb_substr"))
        return mb_substr($str, $start, $length, $charset);
    elseif (function_exists('iconv_substr')) {
        return iconv_substr($str, $start, $length, $charset);
    }
    $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
    $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
    $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
    $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
    preg_match_all($re[$charset], $str, $match);
    $slice = join("", array_slice($match[0], $start, $length));
    if ($suffix){
        return $slice . "...";
    }
    return $slice;
}

/**
 *  判断是否为数组
 *  如果是数组则将json转换为标准的数组
 *  $data  数组
 *  $parse_str 将数组转换为字符串
 */

function if_array(&$data, $parse_str = false){
	if(is_string($data)) {
		$data = object_array(json_decode($data));
		if(is_array($data)) {
			if($parse_str) {
				$data = implode(',', $data);
			}
			return true;
		}else {
			return false;
		}
	}else {
		return false;
	}
}

/**
 * 系统非常规MD5加密方法
 * @param  string $str 要加密的字符串
 * @return string 
 */
function encrypt_password($str){
        $key =C('UC_AUTH_KEY');
	return '' === $str ? '' : md5(sha1($str) . $key);
}


/**
 * 请求一个服务层Api
 * @param string $func    	api 路径, 	ex. Wap.Address.getAllAddressByUser
 * @param array $params	api调用参数
 * @return NULL
 */
function request_core_api($func, $params=array() ){
        import('Curl');
	$curl = new \Library\Curl();
	$curl->setOpt(CURLOPT_TIMEOUT, 3);
	$curl->setOpt(CURLOPT_HTTPHEADER, 0);
	$curl->setOpt(CURLOPT_FRESH_CONNECT, true);
	$curl->setOpt(CURLOPT_ENCODING , 'gzip');
	$curl->setOpt(CURLOPT_USERAGENT, 'VACN_WAP_UI_LAYER');
	
	$func = substr_count($func, '.')===1 ?  C('CORE_API_DEFAULT_MODULE').'.'.$func : $func;
	$params = array(
			'method' => 'Core',
			'func' =>  $func,
			'params' =>  json_encode($params),
	);
	$params['signKey'] = md5($params['params'].C('CORE_API_REQUEST_SECKEY').$params['func']);
	
	$requestEntry = C('CORE_API_HOST').C('CORE_API_HOST_ENTRY');
	
	$curl->post($requestEntry,$params);

	if ($curl->error)
		return null;

	return  json_decode($curl->response, true);
}

 /**发送验证码 （自定义内容） 自定义验证码
     * @param string $numbers 短信号码  可以是数组（多个号码）  也可以是  字符串（一个号码）
     *@param string $content 短信内容
     * @param string $time 发送时间
     * 
     */
    function sendSms($params){
        $res = request_core_api('Sms.sendSMS', $params);
        return $res;
        
    }
    
    /**发送邮件
    * @param array $tos 收件人邮箱  可以是字符串（一个邮箱） 可以是数组  多个邮箱
    * @param string $subject 主题  
    * @param string $body 邮件内容
    * @param type $params
    */
    function sendEmail($params){
        $res = request_core_api('Email.sendEmail', $params);
        return $res;
        
    }

?>
