<?php
    class Pressure extends CI_Controller
    {
        function __construct()
        {
            parent::__construct();
        }

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
    }
