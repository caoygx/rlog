# 功能描述
1. 记录接口请求日志及详细参数，以及执行的sql。让你能了解用户的行为，而且可以还原用户操作。sql记录，让你一目了然地知道这个接口操作了什么表。  
  

# 安装(install)
1. composer require rrbrr/rlog
2. 将根目录下db.sql中的表导入到数据库中



# 使用方法

1. 记录请求参数,将请求参数记录到log_request表中
```php

$whiteList =['127.0.0.1'];
$requestParams = get_http_request_data($whiteList);
if(!is_array($data)){
    $logId = Db::name('log_request')->insertGetId($data);
}
        
```
$white_list 参数可以指定哪些ip请求要做记录，比如只有公司ip访问才会有记录，这样方便公司开发人员随便拿到请求信息做调试。

如果想记录运行输出结果，需要根据自己的框架使用方法，将结果更新到response字段中。
    
2. 记录输出内容,使用下面代码会将curl请求参数和响应结果记录到log_curl表
```php
 $dbConfig = [
    'database_type' => 'mysql',
    'database_name' => 'test',
    'server'        => 'localhost',
    'username'      => 'root',
    'password'      => '123456'
];
$data = [];
$data['user_id'] = 123;
$objCurl = new \rlog\CurlLog($dbConfig);
$objCurl->curlPost($url,$data,$headers);
```
# 效果预览

### 请求日志
![请求日志](https://github.com/caoygx/ThinkphpLogAndErrorAlarm/raw/master/assets/request_log.png)