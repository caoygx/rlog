<?php

namespace rlog;
class CurlLog
{
    protected $db;
    protected $logId;
    function __construct($dbConfig)
    {
        $this->db = new \Medoo\Medoo($dbConfig);
    }

    function write_curl_request_log($url, $method, $requestData)
    {
        $this->logId = 0;
        $data           = [];
        $data['url']    = $url;
        $data['method'] = $method;
        $data['detail'] = $requestData;
        try {
            $r  = $this->db->insert('log_curl', $data);
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
        $this->logId = $this->db->id();
        return $this->logId;
    }

    function write_curl_response_log($responseData)
    {
        $data['response'] = $responseData;
        $r = $this->db->update('log_curl', $data,['id'=>$this->logId]);
    }

    function curlPost($url, $data, $headers = [], $timeout = 100, $responseIsJson = true, $method = 'post')
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
        curl_setopt($ch, CURLOPT_PROXY, '192.168.16.98:8888');
        //}
        $ret = curl_exec($ch);
        if (empty($ret)) {
            //var_dump(curl_error($ch)); // 查看报错信息
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
            $this->write_curl_request_log($url, 'post', $requestHeader . $requestBody);
        } catch (Exception $e) {

        }

        //响应头
        $header_size     = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($ret, 0, $header_size);
        $responseBody    = substr($ret, $header_size);
        //var_dump($responseHeaders);
        try {
            $this->write_curl_response_log($ret);
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
