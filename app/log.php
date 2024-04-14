<?php

use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class Logs
{
    private static $instance = null; // Static field to store the class instance

    private function __construct()
    {
        $logger = new Logger('BSB ' . $_ENV['ENVIRONMENT']);
        if ($_ENV['ENVIRONMENT'] === 'developer') {
            $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://output', Logger::INFO));
        } else {
            $logger->pushHandler(new LogtailHandler($_ENV['LOGTAIL_TOKEN']));
        }
        self::$instance = $logger;
    }

    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            new self(); // Create instance of this class if not already created
        }
        return self::$instance; // Return the Logger instance
    }
}
