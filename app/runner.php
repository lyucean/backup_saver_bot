<?php
require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('PERIOD_SECONDS_RUN')->notEmpty();
$dotenv->required('MAX_EXECUTION_TIME')->notEmpty();

$logFile_success = 'logs/success_runner.log'; // Где будем хранить логи работы бота
$logFile_error = 'logs/error_runner.log'; // Где будем хранить логи работы бота
$targetScript = dirname(__FILE__) . '/main.php'; // Путь к целевому скрипту
$period_main = $_ENV['PERIOD_SECONDS_RUN']; // Раз во сколько минут будет запускаться main.php
$period_runner = $_ENV['MAX_EXECUTION_TIME']; // Раз во сколько минут будет перезапускаться runner.php
set_time_limit(0); // Устанавливаем бесконечное время, т.к. мы будем сами его перезапускать.


// Проверяем, существует ли файл логов, если нет - создадим
if (!file_exists($logFile_success)) {
    touch($logFile_success);
    chmod($logFile_success, 0777); // поправим права
}
if (!file_exists($logFile_error)) {
    touch($logFile_error);
    chmod($logFile_error, 0777); // поправим права
}

// Проверяем количество строк в файле и удаляем первые 1000 строк, если нужно
$logContents = file($logFile_success);
if (count($logContents) >= 2000) {
    $logContents = array_slice($logContents, 1000);
    file_put_contents($logFile_success, implode('', $logContents));
}

// Бесконечный цикл, который будет вызывать основной файл скрипта
while (true) {
    // Засекаем время до выполнения скрипта
    $startTime = microtime(true);

    // Выполняем целевой скрипт и сохраняем вывод в переменную
    $command = "php $targetScript";
    $output = [];
    exec($command, $output);

    // Засекаем время после выполнения скрипта и вычисляем разницу в миллисекундах
    $executionTimeMs = (microtime(true) - $startTime) * 1000;

    // Записываем вывод и время выполнения в лог файл
    $logMessage = date('Y-m-d H:i:s') . " : Время выполнения: " . number_format($executionTimeMs, 2) . " ms\n";
    $logMessage .= '    ' . implode("\n", $output) . PHP_EOL;
    file_put_contents($logFile_success, $logMessage, FILE_APPEND);

    sleep($period_main); // Задержка в секундах перед каждой итерацией цикла

    // Проверяем, если скрипт работает больше нужного, перезапустим его
    if (time() - $_SERVER['REQUEST_TIME'] >= $period_runner) {
        exec('php ' . __FILE__ . ' >> ' . $logFile_error . ' 2>&1 &'); // Запускаем новый экземпляр скрипта
        exit(); // Завершаем текущий экземпляр скрипта
    }
}
