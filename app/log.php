<?php

use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class CustomLogger
{
    private Logger $logger;
    private mixed $log_file;
    private bool $useLogtail;
    private int $pid;

    public function __construct(int $pid)
    {
        // Определяем, используем ли мы Logtail или логирование в файл
        $this->useLogtail = isset($_ENV['LOGTAIL_TOKEN']);

        $this->pid = $pid;

        if ($this->useLogtail) {
            $this->logger = new Logger($this->pid);
            $this->logger->pushHandler(new LogtailHandler($_ENV['LOGTAIL_TOKEN']));
        }

        $this->log_file = $_ENV['LOG_FILE'] ?? 'default.log'; // Устанавливаем значение по умолчанию для файла логов
    }

    public function error($message): void
    {
        $this->log('error', $message);
    }

    public function info($message): void
    {
        $this->log('info', $message);
    }

    public function notice($message): void
    {
        $this->log('notice', $message);
    }

    public function warning($message): void
    {
        $this->log('warning', $message);
    }

    private function log($level, $message): void
    {
//        if ($this->useLogtail) {
            // Используем Monolog для отправки сообщений в Logtail
            $this->logger->{$level}($message, [
              'pid' => $this->pid
            ]);
//        } else {
            // Или записываем логи в файл
            $logMessage = date('Y-m-d H:i:s')."|$this->pid|"." [$level] $message".PHP_EOL;
            error_log($logMessage, 3, $this->log_file);
//        }
    }
}
