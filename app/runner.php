<?php
/**
 * Этот скрипт запускает основной скрипт бота $periodChecked секунду и пишет логи от его выполнения.
 */

$max_execution_time = 60; // Зададим максимальное время выполнения нашего скрипта
$logFile_success = 'logs/success_runner.log'; // Где будем хранить логи работы бота
$logFile_error = 'logs/error_runner.log'; // Где будем хранить логи работы бота
$targetScript = dirname(__FILE__) . '/main.php'; // Путь к целевому скрипту
$periodChecked = 10; // Период проверки скрипта

// Проверяем, существует ли файл логов, если нет - создадим
if (!file_exists($logFile_success)) {
    touch($logFile_success);
    chmod($logFile_success, 0777); // поправим права
}
if (!file_exists($logFile_error)) {
    touch($logFile_error);
    chmod($logFile_error, 0777); // поправим права
}

// Устанавливаем максимальное время выполнения скрипта в 60 секунд
set_time_limit(60);

// Бесконечный цикл, который будет вызывать основной файл скрипта
while (true) {
    // Засекаем время до выполнения скрипта
    $startTime = microtime(true);

    // Выполняем целевой скрипт и сохраняем вывод в переменную
    $command = "php $targetScript";
    $output = [];
//    exec($command, $output);

    // Засекаем время после выполнения скрипта и вычисляем разницу в миллисекундах
    $endTime = microtime(true);
    $executionTimeMs = ($endTime - $startTime) * 1000;

    // Записываем вывод и время выполнения в лог файл
    $logMessage = date('Y-m-d H:i:s') . " : Execution time: " . number_format($executionTimeMs, 2) . " ms\n";
    $logMessage .= '    ' . implode("\n", $output) . PHP_EOL;
    file_put_contents($logFile_success, $logMessage, FILE_APPEND);

    // Завершаем текущую итерацию, чтобы избежать нагрузки на сервер
    sleep($periodChecked); // Задержка в секундах перед каждой итерацией цикла

    // Определяем текущее время
    $currentTime = time();

    // Проверяем, если скрипт работает больше нужного, перезапустим его
    if ($currentTime - $_SERVER['REQUEST_TIME'] >= $max_execution_time) {
        // Запускаем новый экземпляр скрипта
        exec('php ' . __FILE__ . ' >> ' . $logFile_error . ' 2>&1 &');
        exit(); // Завершаем текущий экземпляр скрипта
    }
}
