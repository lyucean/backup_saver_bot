<?php
require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('PERIOD_START_RUNNER')->notEmpty();
$dotenv->required('PERIOD_START_MAIN')->notEmpty();
$dotenv->required('ENVIRONMENT')->notEmpty();

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
      'environment' => $_ENV['ENVIRONMENT']
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
$startTime = time();

// Получаем ID текущего процесса
$pid = getmypid();

$logMessage = date('Y-m-d H:i:s') . " Запуск Runner - '$pid' " . PHP_EOL;

// Бесконечный цикл, который будет вызывать основной файл скрипта
while (true) {

    // Выполняем целевой скрипт и сохраняем вывод в переменную
    $command = "php $targetScript";
    $output = [];
    exec($command, $output);

    // Записываем вывод и время выполнения в лог файл
    $logMessage .= date('Y-m-d H:i:s')
                . " : Время выполнения: " . number_format((microtime(true) - $startTime) * 1000, 2) . " ms" . PHP_EOL;
    $logMessage .= '    ' . implode("\n", $output) . PHP_EOL;
    file_put_contents($log_file, $logMessage, FILE_APPEND);

    sleep($period_main); // Задержка в секундах перед каждой итерацией цикла

    // Проверяем, если скрипт работает больше нужного, перезапустим его
    if ((time() - $startTime) >= $period_runner) {
        exec('php ' . __FILE__ . ' >> ' . $log_file . ' 2>&1 &'); // Запускаем новый экземпляр скрипта
        exec("kill -9 $pid"); // Завершаем предыдущий процесс, на всякий случай, если он завис.
        exit(); // Завершаем текущий экземпляр скрипта.
    }
}
