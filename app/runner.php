<?php

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('PERIOD_START_RUNNER')->notEmpty();
$dotenv->required('PERIOD_START_MAIN')->notEmpty();
$dotenv->required('ENVIRONMENT')->notEmpty();
$dotenv->required('LOG_FILE')->notEmpty();

if ($_ENV['ENVIRONMENT'] == 'developer') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Копим логи ошибок в Sentry
if (!empty($_ENV['SENTRY_DNS'])) {
    \Sentry\init([
      'dsn' => $_ENV['SENTRY_DNS'],
      'release' => date("Y-m-d_H.i", filectime(__FILE__)), //тест релиза
      'environment' => $_ENV['ENVIRONMENT'],
      'traces_sample_rate' => 0.2,
    ]);
}

$log_file = $_ENV['LOG_FILE']; // Где будем хранить логи работы runner
$targetScript = dirname(__FILE__).'/main.php'; // Путь к целевому скрипту
$period_runner = $_ENV['PERIOD_START_RUNNER']; // Раз во сколько секунд будет перезапускаться runner.php
$period_main = $_ENV['PERIOD_START_MAIN']; // Раз во сколько минут будет запускаться main.php
set_time_limit(0); // Устанавливаем бесконечное время, т.к. мы будем сами его перезапускать.
date_default_timezone_set('Europe/Moscow'); // московский регион
$pid = getmypid(); // Получаем ID текущего процесса


// Проверяем, существует ли файл логов, если нет - создадим
if (!file_exists($log_file)) {
    touch($log_file);
    chmod($log_file, 0777); // поправим права
}

// Функция записи логов
$log = function ($logMessage) use ($log_file, $pid) {

    // Проверяем количество строк в файле и удаляем первые 1000 строк, если нужно
    $logContents = file($log_file);
    $totalLines = count($logContents);

    if ($totalLines >= 1500) {
        $logContents = array_slice($logContents, $totalLines - 1000); // Оставляем только последние 1000 строк
        file_put_contents($log_file, implode('', $logContents));
    }

    // Дописываем нашу строку
    file_put_contents($log_file, date('Y-m-d H:i:s')." - $pid: ".$logMessage.PHP_EOL, FILE_APPEND);
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
        exec("php $targetScript > /dev/null 2>&1 &");
    }

    $log("Осталось: ".($period_runner - (time() - $startRunnerTime))." сек.");
    sleep(1); // Отмеряем секунды.
}

exec('php '.__FILE__.' >> '.$log_file.' 2>&1 &'); // Запускаем новый экземпляр скрипта
exit(); // Завершаем текущий экземпляр скрипта.