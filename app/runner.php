<?php
require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('PERIOD_START_RUNNER')->notEmpty();
$dotenv->required('PERIOD_START_MAIN')->notEmpty();
$dotenv->required('ENVIRONMENT')->notEmpty();

if($_ENV['ENVIRONMENT'] == 'developer'){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$log_file = 'logs/runner.log'; // Где будем хранить логи работы runner
$targetScript = dirname(__FILE__) . '/main.php'; // Путь к целевому скрипту
$period_runner = $_ENV['PERIOD_START_RUNNER']; // Раз во сколько секунд будет перезапускаться runner.php
$period_main = $_ENV['PERIOD_START_MAIN']; // Раз во сколько минут будет запускаться main.php
set_time_limit(0); // Устанавливаем бесконечное время, т.к. мы будем сами его перезапускать.
date_default_timezone_set('Europe/Moscow'); // московский регион

// Проверяем, существует ли файл логов, если нет - создадим
if (!file_exists($log_file)) {
    touch($log_file);
    chmod($log_file, 0777); // поправим права
}

// Проверяем количество строк в файле и удаляем первые 1000 строк, если нужно
$logContents = file($log_file);
if (count($logContents) >= 2000) {
    $logContents = array_slice($logContents, 1000);
    file_put_contents($log_file, implode('', $logContents));
}

// Получаем ID текущего процесса
$pid = getmypid();

// Функция логов
$log = function ($logMessage) use ($log_file, $pid) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $pid: " . $logMessage . PHP_EOL, FILE_APPEND);
};

$log("Старт Runner");

$startRunnerTime = time(); // Засекаем время до выполнения скрипта

// Бесконечный цикл, который будет вызывать основной файл скрипта
while (true) {
    if (time() - $startRunnerTime >= $period_runner) {
        $log("Завершаем Runner");
        break;
    }

    // Проверяем, если прошло достаточно времени для запуска целевого скрипта
    if ((time() - $startRunnerTime) % $period_main == 0) {
        $log("Запуск целевого скрипта");
        exec("php $targetScript");
    }

    $log("Осталось: " . ($period_runner - (time() - $startRunnerTime)) . " сек.");
    sleep(1); // Отмеряем секунды.
}

exec('php ' . __FILE__ . ' >> ' . $log_file . ' 2>&1 &'); // Запускаем новый экземпляр скрипта
exit(); // Завершаем текущий экземпляр скрипта.