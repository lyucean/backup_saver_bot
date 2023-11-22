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

// Копим логи ошибок в Sentry
if (!empty($_ENV['SENTRY_DNS'])) {
    \Sentry\init([
      'dsn' => $_ENV['SENTRY_DNS'],
      'release' => date("Y-m-d_H.i", filectime(__FILE__)), //тест релиза
      'environment' => $_ENV['ENVIRONMENT'],
      'traces_sample_rate' => 0.2,
    ]);
}

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

// Засекаем время до выполнения скрипта
$startRunnerTime = time();

// Получаем ID текущего процесса
$pid = getmypid();

// Функция логов
$log = function ($logMessage) {
    global $log_file, $pid;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $pid: " . $logMessage . PHP_EOL, FILE_APPEND);
};

$log("Старт Runner - $pid");

// Бесконечный цикл, который будет вызывать основной файл скрипта
while (true) {

    // Выполняем целевой скрипт и сохраняем вывод в переменную
    $command = "php $targetScript";
    $output = [];
    $startMainTime = time();
    exec($command, $output);

    // Записываем вывод и время выполнения в лог файл
    $log("Время выполнения: " . number_format((time() - $startMainTime), 2) . " сек");
    $log(implode("\n", $output));

    sleep($period_main); // Задержка в секундах перед каждой итерацией цикла

    // Проверяем, если скрипт работает больше нужного, перезапустим его
    if ((time() - $startRunnerTime) >= $period_runner) {
        $log("Завершаем Runner - $pid");
        exec('php ' . __FILE__ . ' >> ' . $log_file . ' 2>&1 &'); // Запускаем новый экземпляр скрипта
        exit(); // Завершаем текущий экземпляр скрипта.
    }
}
