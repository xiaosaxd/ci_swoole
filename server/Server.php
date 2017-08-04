<?php
    class Server
    {
        private $serv;
        function __construct()
        {
            $conf = parse_ini_file(__DIR__.'/swoole.ini');

            $this->serv = new swoole_http_server($conf['host'], $conf['port']);
            $this->serv->set([
                'worker_num' => $conf['worker_num'],
                'max_request' => $conf['max_request'],
                'daemonize' => $conf['daemonize'] != '' ? $conf['daemonize'] : ($conf['env'] == 'development' ? false : true),
                'log_file' => $conf['log_file']
            ]);

            $this->serv->on('workerStart', [$this, 'onWorkerStart']);
            $this->serv->on('request', [$this, 'onRequest']);
            $this->serv->on('shutdown', [$this, 'onShutdown']);

            $this->serv->start();
        }

        function onWorkerStart(swoole_server $server)
        {
            try
            {
                require_once dirname(__DIR__).'/swoole_constant.php';
                self::log('worker start');
            }
            catch(Exception $e)
            {
                self::log($e->getMessage());
                $server->shutdown();
            }
        }

        function onRequest(swoole_http_request $request, swoole_http_response $response)
        {
            if($request->server['request_uri'] == '/favicon.ico')
            {
                //过滤favicon.ico,在nginx处理
                return;
            }

            unset($_SERVER);
            foreach($request->header as $k => $v)
            {
                $_SERVER['HTTP_'.strtoupper($k)] = $v;
            }
            foreach($request->server as $k => $v)
            {
                $_SERVER[strtoupper($k)] = $v;
            }
            unset($_GET);
            $_GET = empty($request->get) ? [] : $request->get;
            unset($_POST);
            $_POST = empty($request->post) ? [] : $request->post;
            unset($_COOKIE);
            $_COOKIE = empty($request->cookie) ? [] : $request->cookie;
            unset($_FILES);
            $_FILES = empty($request->files) ? [] : $request->files;
            unset($GLOBALS);
            $GLOBALS['rawContent'] = empty($request->rawContent()) ? '' : $request->rawContent();

            try
            {
                ob_start();
                include SWOOLE_SERVER_PATH . 'Codeigniter_handle.php';
                $result = ob_get_contents();
                ob_clean();

                $response->end($result);
            }
            catch(Exception $e)
            {
                $response->end($e->getMessage());
            }

        }

        function onShutdown()
        {
            self::log('server is shutdown');
        }

        static function log($msg)
        {
            printf('[%s]: %s'.PHP_EOL, date('Y-m-d H:i:s', time()), $msg);
        }
    }

    new Server();