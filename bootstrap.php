<?php

function get_raw_headers($raw = false)
{
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $key           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$key] = $value;
        }
    }
    if ($raw) {
        $str = "";
        foreach ($headers as $k => $v) {
            $str .= "$k: $v\r\n";
        }
        return $str;
    }
    return $headers;
}

function get_http_request_data($ipWhiteList = [])
{
    try {
        $isPost = $isGet = false;
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $isPost = true;
        }
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $isGet = true;
        } else {

        }

        //不需要记录的url地址
        $blacklist = [
            "/index/news/getList",
        ];

        foreach ($blacklist as $v) {
            if (strpos($_SERVER['REQUEST_URI'], $v) !== false) return;
        }

        $data        = array();
        $data['url'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        if ($isPost) {
            $params = $_POST;
        } elseif ($isGet) {
            $params = $_GET;
        }
        if (empty($params)) $params['input'] = file_get_contents("php://input");
        $data['params'] = json_encode($params);
        $data['ip']     = get_client_ip_for_log();
        //$ipWhiteList    = ['127.0.0.1', '192.168.16.96', '127.0.0.1', '192.168.16.118'];
        if (!empty($ipWhiteList) && !in_array($data['ip'], $ipWhiteList)) return [];
        $detail            = array();
        $detail['request'] = $_REQUEST;

        $header = [];
        $fields = ['HTTP_USER_ID', 'HTTP_DEVICE_VID', 'HTTP_DEVICE_ID', 'HTTP_PLATFORM', 'HTTP_VERSION']; //'HTTP_USER_AGENT',
        foreach ($fields as $k => $v) {
            if (empty($_SERVER[$v])) continue;
            $header[$v] = $_SERVER[$v];
        }

        $url     = $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . " " . $_SERVER['SERVER_PROTOCOL'] . "\r\n";
        $request = $url . get_raw_headers(true);

        $raw_post = '';
        if ($isPost) {
            $raw_post = http_build_query($_POST);
            if (empty($raw_post)) {
                $raw_post = file_get_contents("php://input");
            }
        }
        $request .= "\r\n" . $raw_post;

        $data['detail']     = $request;
        $data['user_agent'] = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];
        //$data['user_id'] = $_GET['user_id'];////cookie可能取出null,要求字段必须可为null
        if (empty($data['user_id'])) {
            //$userInfo = cookie('LOGIN_USER');
            //$user_id = $userInfo['user_id'];
            //$data['user_id'] = $user_id;
        }
        $data['create_time'] = date("Y-m-d H:i:s");
        $data['method']      = $_SERVER['REQUEST_METHOD'];
        //$data['date_int'] = time();
        return $data;
    } catch (\Exception $e) {
        return $e->getMessage();
        //exit;
        //tplog($e->getMessage());
    }
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip_for_log($type = 0, $adv = false)
{
    $type = $type ? 1 : 0;
    static $ip = null;
    if (null !== $ip) {
        return $ip[$type];
    }
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}
