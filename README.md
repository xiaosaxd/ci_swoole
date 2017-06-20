#ciswoole
--
整合了Codeigniter和Swoole扩展的web框架,底层使用swoole_http_server提供服务,上层应用使用codeigniter框架搭建

##使用
1.  cd server
1.  修改配置文件swoole.ini
1.  启动服务: php Server.php
1.  客户端访问127.0.0.1:9999/controller/method
1.  当代码有更新时,需要执行`bash bash/reload.sh`重启worker进程

##改造思路
1.  CI执行单个请求的过程包括全局加载(包括一些常量的定义以及文件的引入)以及处理请求两部分.
    -   将全局加载放到workerStart回调中,避免每个请求的重复加载.
    -   将处理请求部分放到onRequest回调里.
1.  传统的nginx+fpm在执行请求的过程,nginx会先将请求处理成符合fastcgi标准,传递到fpm,php解释器会进行处理并填充超全局变量
    -   在onRequest回调中添加对超全局变量的填充.
1.  worker进程执行到exit/die会退出.
    -   将所有的exit/die改为抛出异常的方式,在onRequest回调中捕获异常来处理(注,自己编写代码的时候不能用exit/die)
1.  其他一些处理
    -   load_class中采用的是单例的模式,跟请求相关的对象(URI和Router)需要在每次请求的时候重新实例化
    -   修复因为超全局变量没有百分百填充(如$_SERVER['SCRIPT_NAME'])导致可能存在的一些问题

##测试
###本机环境:
1.  php : 7.0.7
1.  swoole : 1.9.13
1.  cpu 2核

###压测脚本
```
    function test()
    {
    
        static $mysql = '';
        if(empty($mysql))
        {
            $mysql = new mysqli('127.0.0.1', 'Abel', '123456', 'test');
        }
        
        $mysql->query('insert into pressure (val) values ("123")');
        usleep(50000);
        file_put_contents('a.txt', time().PHP_EOL, FILE_APPEND);

        echo 'success';
    }
    
```

###压测结果
1.  nginx + fpm(pm:static/max_children=100)
1.  swoole(按照12核机器算,这里开48个worker)
