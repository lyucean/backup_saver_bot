<?php
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

require_once('vendor/autoload.php');
require_once('SQLiteConnection.php');

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

// Копим логи ошибок в Sentry
if (!empty($_ENV['SENTRY_DNS'])) {
    \Sentry\init([
      'dsn' => $_ENV['SENTRY_DNS'],
      'environment' => $_ENV['ENVIRONMENT'],
      'release' =>  'bsb@' . date("Y-m-d_H.i", filectime(__FILE__))
    ]);
}


// Подключим класс логов
$logger = new Logger("example");
$logger->pushHandler(new LogtailHandler($_ENV['LOGTAIL_TOKEN']));
$logger->info("Запуск Runner");

// Параметры подключения к WebDAV Яндекс Диска
$baseUri = $_ENV['WEBDAV_SERVER'];
$username = $_ENV['WEBDAV_USERNAME'];
$password = $_ENV['WEBDAV_PASSWORD'];
$maximum_storage_day = $_ENV['MAXIMUM_STORAGE_DAY'];

// Создаем клиент WebDAV
$client = new Sabre\DAV\Client([
  'baseUri' => $baseUri,
  'userName' => $username,
  'password' => $password,
]);

// Путь к папке бекапов на WEBDAV_SERVER
$webdav_folder = $_ENV['WEBDAV_FOLDER'];

// Проверяем существование папки на Яндекс Диске
//try {
//    $response = $client->request('PROPFIND', '/'.$webdav_folder);
//    if ($response['statusCode'] === 404) { // если не существует, вернёт 404
//        // Папка не существует на сервере
//        try {
//            $client->request('MKCOL', '/'.$webdav_folder); // Создаем папку на Яндекс Диске
//            echo "Папка '$webdav_folder' успешно создана на Яндекс Диске.\n";
//        } catch (Exception $e) {
//            echo "Ошибка при создании папки '$webdav_folder' на Яндекс Диске: ".$e->getMessage()."\n";
//        }
//    }
//} catch (Exception $e) {
//    echo "Ошибка при проверки папки '$webdav_folder' на Яндекс Диске: ".$e->getMessage()."\n";
//}

// Папка, в которой хранятся бекапы
$backupFolder = 'backups';

// Маска для поиска файлов бекапа
$fileMask = $_ENV['FILE_MASK'];


// Подключение к базе данных SQLite
$db = new SQLiteConnection();
$db->createTable(); // Создаем таблицу, если она не существует

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
            $logger->error("Файл '$filename' уже отправлен на Яндекс Диск, пропускаем.");
        }
    }
} else {
    $logger->error("Файлы с маской '$fileMask' не найдены в папке '$backupFolder'.");
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
        echo "Файл '$filename' не существует на Яндекс Диске." . PHP_EOL;
    }

    $db->markFileAsDeleted($filename); // Помечаем файл как удаленный в базе данных
    echo "Запись о файле '$filename' помечена как удаленная в базе данных." . PHP_EOL;
}

$db->close(); // Закрываем соединение с базой данных
