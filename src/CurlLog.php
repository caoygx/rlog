<?php

namespace rlog;
class CurlLog
{
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


    function write_curl_request_log($url, $method, $requestData)
    {
        $data           = [];
        $data['url']    = $url;
        $data['method'] = $method;
        $data['detail'] = $requestData;

        $sql = "INSERT INTO " . 'log_curl' . " (`url`,`method`,`detail`) VALUES('{$data['url']}','{$data['method']}','{$data['detail']}')";

        try {
            $database = new \Medoo\Medoo([
                'database_type' => 'mysql',
                'database_name' => 'test',
                'server'        => 'localhost',
                'username'      => 'root',
                'password'      => '123456'
            ]);
            $r        = $database->insert('log_request', $data);
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
        $log_id = $database->id();
        return $log_id;

    }

    function write_curl_response_log($responseData)
    {
        global $_W;
        $sql = "update  " . 'log_curl' . " set response='{$responseData}'";
        //echo $sql;
        //pdo_query($sql);
    }

    function curl_post($url, $data, $headers = [], $timeout = 100, $responseIsJson = true, $method = 'post')
    {

        $ch = curl_init();
        if ($method == 'get') {

        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }


        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //不输出内容到页面

        //设置请求头响应头
        curl_setopt($ch, CURLOPT_HEADER, true); //return header of response
        curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE); //get request header


        //设置超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout - 2);
        //curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727;cli-test)');

        //curl_setopt($ch, CURLOPT_VERBOSE, 1);


        //https证书
        $CA     = false;
        $caCert = getcwd() . '/cacert.pem'; // CA根证书
        $SSL    = substr($url, 0, 8) == "https://" ? true : false;
        if ($SSL) {
            if ($CA) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 只信任CA颁布的证书
                curl_setopt($ch, CURLOPT_CAINFO, $caCert); // CA根证书（用来验证的网站证书是否是CA颁布）
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名，并且是否与提供的主机名匹配
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 检查证书中是否设置域名
            }
        }

        //代理
        //if(C('test_proxy')) {
        curl_setopt($ch, CURLOPT_PROXY, '192.168.16.96:8888');
        //}

        $ret = curl_exec($ch);
        //var_dump($ret);exit('xx');
        if (empty($ret)) {
            var_dump(curl_error($ch)); // 查看报错信息
            try {
                //write_curl_response_log(curl_error($ch));
            } catch (Exception $e) {

            }
            return false;
        }

        //请求信息,如果网络异常也没有请求信息
        $requestHeader = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        if (is_string($data)) {
            $requestBody = $data;
        } else {
            $requestBody = var_export($data, 1);
        }
        try {
            write_curl_request_log($url, 'post', $requestHeader . $requestBody);
        } catch (Exception $e) {

        }

        //响应头
        $header_size     = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($ret, 0, $header_size);
        $responseBody    = substr($ret, $header_size);
        //var_dump($responseHeaders);
        try {
            write_curl_response_log($ret);
        } catch (Exception $e) {

        }
        curl_close($ch);
        if ($responseIsJson) {
            $responseBody = json_decode($responseBody, true);
            $error        = json_last_error();
            if (!empty($error)) {
                return $error;
            }
        }
        return $responseBody;

    }
}
