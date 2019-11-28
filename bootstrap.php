<?php
if (!function_exists('write_sql_log')) {
    function write_sql_log($pdo, $sql, $params)
    {
        try {
            global $_W;
            $logId = $_W['logId'];
            if (!empty($logId) && strpos($sql, 'log_request') === false) {
                $dbLogDir = IA_ROOT . "/db_log";
                if (!file_exists($dbLogDir)) {
                    mkdir($dbLogDir);
                }
                $realSql = get_real_sql($pdo, $sql, $params);

                file_put_contents($dbLogDir . "/$logId.sql", $realSql . ';' . PHP_EOL, FILE_APPEND);
            }
        } catch (Exception $e) {

        }
    }
}
if (!function_exists('get_real_sql')) {
    function get_real_sql($pdo, $sql, $bind = [])
    {
        if (is_array($sql)) {
            $sql = implode(';', $sql);
        }

        foreach ($bind as $key => $val) {
            $value = is_array($val) ? $val[0] : $val;
            $type  = is_array($val) ? $val[1] : PDO::PARAM_STR;
            if (PDO::PARAM_STR == $type) {
                //$value = $value;
                $value = $pdo->quote($value);
            } elseif (PDO::PARAM_INT == $type) {
                $value = (float)$value;
            }
            // 判断占位符
            $sql = is_numeric($key) ?
                substr_replace($sql, $value, strpos($sql, '?'), 1) :
                str_replace(
                    [$key . ')', $key . ',', $key . ' ', $key . PHP_EOL],
                    [$value . ')', $value . ',', $value . ' ', $value . PHP_EOL],
                    $sql . ' ');
        }
        return rtrim($sql);
    }
}


if (!function_exists('write_request_log')) {
    function write_request_log()
    {
        /*$data = get_request_raw();
        $result = $m->insert($data);
        $logId = $m->getLastInsID();*/
    }
}

if (!function_exists('get_raw_headers')) {
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
}

if (!function_exists('get_http_request_data')) {
    function get_http_request_data()
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
            $ipWhiteList    = ['127.0.0.1', '192.168.16.96', '127.0.0.1', '192.168.16.118'];
            if (!empty($ipWhiteList) && !in_array($data['ip'], $ipWhiteList)) return;
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
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
if (!function_exists('get_client_ip_for_log')) {
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
}


//
//ini_set("display_errors","On");
//error_reporting(E_ALL ^ E_NOTICE^E_DEPRECATED);
//actionBegin();


//使用smtp
function sendmail($subject, $body, $to, $toname, $from = "", $fromname = '项目名称', $altbody = '邮件', $wordwrap = 80, $mailconf = '')
{

    $mail = new \PHPMailer();
    $mail->IsSMTP(); // telling the class to use SMTP
    $mail->SMTPDebug = 0;                   // enables SMTP debug // 1 = errors and messages// 2 = messages only
    $mail->Timeout   = 3;

    $mail->SMTPAuth = true;                  // enable SMTP authentication
    $mail->Host     = config('mail.host'); // sets the SMTP server
    $mail->Port     = config('mail.port');                    // set the SMTP port for the GMAIL server
    $mail->Username = config("mail.username"); // SMTP account username
    $mail->Password = config('mail.password');        // SMTP account password

    $from = config("mail.username");
    $mail->SetFrom($from, $fromname);

    //$mail->AddReplyTo($to,$toname);
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->SMTPSecure = "ssl"; // SMTP 安全协议
    $mail->Subject    = $subject;
    //$body             = eregi_replace("[\]",'',$body);
    //$mail->AltBody    = "AltBody"; // optional, comment out and test

    $mail->MsgHTML($body);


    if (is_array($to)) {
        foreach ($to as $v) {
            $mail->AddAddress($v, $toname);
        }
    } else {
        $mail->AddAddress($to, $toname);
    }


    //$mail->AddAttachment("images/phpmailer.gif");      // attachment
    //$mail->AddAttachment("images/phpmailer_mini.gif"); // attachment

    if (!$mail->Send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
        return false;
    } else {
        return true;
    }
}

function error_notify($error_content)
{
    $uri           = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $error_content = $uri . "<br />" . $error_content;
    alarm($error_content);
    //$admin_mail = C('admin_eamil');
    //if($admin_mail) $r = sendmail("出错啦！",$error_content,$admin_mail,'');
}

/**
 * 增加队列消息 各controller只有删除自己的队列
 * @param string $module
 * @param string $controller
 * @param string $action
 * @param array $parameter
 */
function addQueue($module, $controller, $action, $parameter)
{
    //加入访问历史
    $qMsg               = [];
    $qMsg['module']     = $module;
    $qMsg['controller'] = $controller;
    $qMsg['action']     = $action;
    $qMsg['parameter']  = $parameter;
    $qMsg               = json_encode($qMsg);
    $data["message"]    = $qMsg;
    \think\Db::name("queue")->insert($data);
}

/**
 * To send alarm when program error
 * @param $content
 */
function alarm($content)
{
    $qHistoryMsgParameter = [
        "content" => $content,
    ];
    addQueue("Index", "monitor", "alarm", $qHistoryMsgParameter);
}


function curl_with_log($url, $data, $headers = [], $options = [], $requestCallback=null, $responseCallback=null)
{

    $timeout        = !empty($options['timeout']) ? $options['timeout'] : 100;
    $responseIsJson = !empty($options['responseIsJson']) ? true : false;
    $method         = $options['method'];

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

    $response = curl_exec($ch);

    //出错
    if (empty($response)) {
        var_dump(curl_error($ch)); // 查看报错信息
        try {
            //writeCurlResponseLog(curl_error($ch));
            if (is_callable($responseCallback)) {
                $responseCallback(curl_error($ch));
            }

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
        $requestData = $requestHeader . $requestBody;
        //writeCurlRequestLog($url, 'post', $requestHeader . $requestBody);
        if (is_callable($requestCallback)) {
            $requestCallback($url,$method, $requestData);
        }
    } catch (Exception $e) {

    }

    //响应头
    $header_size     = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $header_size);
    $responseBody    = substr($response, $header_size);
    try {
        //writeCurlResponseLog($ret);
        if (is_callable($responseCallback)) {
            $responseCallback($response);
        }
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


function writeCurlRequestLog($url, $method, $requestData)
{
    $data           = [];
    $data['url']    = $url;
    $data['method'] = $method;
    $data['detail'] = $requestData;
    $sql            = "INSERT INTO " . 'log_curl' . " (`url`,`method`,`detail`) VALUES('{$data['url']}','{$data['method']}','{$data['detail']}')";
    pdo_query($sql);
    //pdo_insert("log_curl", $requestData);
    $logId = pdo_insertid();
    global $_W;
    $_W['curlLogId'] = $logId;
    header("logId: $logId");
    return $logId;

}

function writeCurlResponseLog($responseData)
{
    global $_W;
    $sql = "update  " . 'log_curl' . " set response='{$responseData}'";
    //echo $sql;
    pdo_query($sql);
}

//调用方法
function curl_post($url, $data,$headers=[],$timeout=100,$responseIsJson=true,$method='post'){

    $ch     = curl_init();
    if($method=='get'){

    }else{
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
    $CA = false;
    $caCert = getcwd() . '/cacert.pem'; // CA根证书
    $SSL    = substr($url, 0, 8) == "https://" ? true : false;
    if ($SSL) {
        if($CA){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 只信任CA颁布的证书
            curl_setopt($ch, CURLOPT_CAINFO, $caCert); // CA根证书（用来验证的网站证书是否是CA颁布）
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名，并且是否与提供的主机名匹配
        }else{
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 检查证书中是否设置域名
        }
    }

    //代理
    //if(C('test_proxy')) {
    //curl_setopt($ch, CURLOPT_PROXY, '192.168.16.96:8888');
    //}

    $ret = curl_exec($ch);
    //var_dump($ret);exit('xx');
    if (empty($ret)) {
        var_dump(curl_error($ch)); // 查看报错信息
        try {
            writeCurlResponseLog(curl_error($ch));
        }catch (Exception $e){

        }
        return false;
    }

    //请求信息,如果网络异常也没有请求信息
    $requestHeader = curl_getinfo($ch,CURLINFO_HEADER_OUT);
    if(is_string($data)){
        $requestBody = $data;
    }else{
        $requestBody = var_export($data,1);
    }
    try{
        writeCurlRequestLog($url,'post',$requestHeader.$requestBody);
    }catch (Exception $e){

    }

    //响应头
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($ret, 0, $header_size);
    $responseBody = substr($ret, $header_size);
    //var_dump($responseHeaders);
    try{
        writeCurlResponseLog($ret);
    }catch (Exception $e){

    }
    curl_close($ch);
    if($responseIsJson){
        $responseBody = json_decode($responseBody,true);
        $error = json_last_error();
        if (!empty($error)) {
            return $error;
        }
    }
    return $responseBody;

}