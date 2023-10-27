<?php

$backupFolder = 'backups'; // Замените на путь к вашей папке
$currentDate = date('Y-m-d');
$currentTimestamp = time();

for ($i = 1; $i <= 5; $i++) {
    $currentTime = date('H:i:s', $currentTimestamp);
    $filename = "BSB_{$currentDate}_{$currentTime}.txt";
    $filePath = "{$backupFolder}/{$filename}";

    // Создание файла
    if (touch($filePath)) {
        echo "Создан файл: {$filePath}\n";
    } else {
        echo "Не удалось создать файл: {$filePath}\n";
    }

    // Увеличение времени на 1 секунду для следующего файла
    $currentTimestamp++;
}
?>