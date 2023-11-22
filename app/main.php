<?php

require_once('vendor/autoload.php');
require_once('db.php');

use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Проверка конфига
$dotenv->required('ENVIRONMENT')->notEmpty();
$dotenv->required('WEBDAV_SERVER')->notEmpty();
$dotenv->required('WEBDAV_USERNAME')->notEmpty();
$dotenv->required('WEBDAV_PASSWORD')->notEmpty();
$dotenv->required('WEBDAV_FOLDER')->notEmpty();
$dotenv->required('FILE_MASK')->notEmpty();
$dotenv->required('BACKUPS_FOLDER')->notEmpty();
$dotenv->required('MAXIMUM_STORAGE_DAY')->notEmpty();
$dotenv->required('PERIOD_START_MAIN')->notEmpty();

set_time_limit($_ENV['PERIOD_START_MAIN'] - 1); // Убиваем MAIN скрипт, если он завис и пришло время запуска нового

// Копим логи ошибок в Sentry
if (!empty($_ENV['SENTRY_DNS'])) {
    \Sentry\init([
      'dsn' => $_ENV['SENTRY_DNS'],
      'environment' => $_ENV['ENVIRONMENT'],
      'release' =>  'bsb@' . date("Y-m-d_H.i", filectime(__FILE__))
    ]);
}

// Подключим класс логов
$logger = new Logger("bsb");
$logger->pushHandler(new LogtailHandler($_ENV['LOGTAIL_TOKEN']));
$logger->notice("Запуск Main - " . getmypid() . ", в окружении: " . $_ENV['ENVIRONMENT']);

// Создаем клиент WebDAV
$client = new Sabre\DAV\Client([
  'baseUri' => $_ENV['WEBDAV_SERVER'],
  'userName' => $_ENV['WEBDAV_USERNAME'],
  'password' => $_ENV['WEBDAV_PASSWORD'],
]);

// Подключение к базе данных SQLite
$db = new SQLite();

$maximum_storage_day = $_ENV['MAXIMUM_STORAGE_DAY']; // Сколько дней храним архив
$webdav_folder = $_ENV['WEBDAV_FOLDER']; // Путь к папке бекапов на WEBDAV_SERVER
$backupFolder = 'backups'; // Папка, в которой хранятся бекапы
$fileMask = $_ENV['FILE_MASK']; // Маска для поиска файлов бекапа

// Получаем список файлов в папке backups
$localFiles = glob($backupFolder . '/' . $fileMask);

if (!empty($localFiles)) {

    $logger->info("Файлы найдены", $localFiles);

    foreach ($localFiles as $localFile) {

        $filename = basename($localFile); // Имя файла без пути

        if (!$db->fileExists($filename)) {

            $logger->info("Отправляем файл '$filename' на Яндекс Диск");

            try {
                $client->request('PUT', '/'.$webdav_folder.'/'.$filename, file_get_contents($localFile));

                $logger->info("Файл '$filename' успешно отправлен на Яндекс Диск.");

                // Записываем информацию о файле в базу данных
                $sent_date = date('Y-m-d H:i:s');
                $db->insertFile($filename, $sent_date);
            } catch (Exception $e) {
                $logger->error("Ошибка при отправке файла '$filename' на Яндекс Диск: ". $e->getMessage());
            }
        } else {
            $logger->warning("Файл '$filename' уже отправлен на Яндекс Диск, пропускаем.");
        }
    }
} else {
    $logger->warning("Файлы с маской '$fileMask' не найдены в папке '$backupFolder'.");
}

// Удаляем файлы, старше 7 дней с сервера, с sqlite и с Яндекс Диска
foreach  ($db->getOldFiles($maximum_storage_day) as $filename) {

    $logger->info("Есть файл '$filename', старше '$maximum_storage_day' дней, отправляем на удаление.");

    // Проверяем, есть ли файл на сервере
    $localFilePath = $backupFolder . '/' . $filename;

    if (file_exists($localFilePath)) {
        unlink($localFilePath); // Удаляем файл с сервера
        $logger->info("Файл '$filename' удален с сервера." );
    }else{
        $logger->error("Файл '$filename' не может быть удалён, т.к. не найден.");
    }

    // Проверяем, есть ли файл на Яндекс Диске
    $remoteFilePath = '/' . $webdav_folder . '/' . $filename;
    try {
        $response = $client->request('HEAD', $remoteFilePath);
        if ($response['statusCode'] === 200) {
            $client->request('DELETE', $remoteFilePath); // Удаляем файл с Яндекс Диска

            $logger->info("Файл '$filename' удален с Яндекс Диска.");
        }
    } catch (Exception $e) {
        $logger->error("Файл '$filename' не существует на Яндекс Диске.");
    }

    $db->markFileAsDeleted($filename); // Помечаем файл как удаленный в базе данных
    $logger->info("Запись о файле '$filename' помечена как удаленная в базе данных.");
}

$db->close(); // Закрываем соединение с базой данных
