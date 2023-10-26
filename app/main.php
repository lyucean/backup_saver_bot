<?php

require_once('vendor/autoload.php');
require_once('SQLiteConnection.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dotenv->required('ENVIRONMENT')->notEmpty();

// Параметры подключения к WebDAV Яндекс.Диска
$baseUri = $_ENV['WEBDAV_SERVER'];
$username = $_ENV['WEBDAV_USERNAME'];
$password = $_ENV['WEBDAV_PASSWORD'];

// Создаем клиент WebDAV
$client = new Sabre\DAV\Client([
  'baseUri' => $baseUri,
  'userName' => $username,
  'password' => $password,
]);

// Путь к папке бекапов на WEBDAV_SERVER
$webdav_folder = $_ENV['WEBDAV_FOLDER'];

// Проверяем существование папки на Яндекс.Диске
$remoteFolderExists = false;
try {
    $response = $client->request('PROPFIND', '/'.$webdav_folder);
    if ($response['statusCode'] === 200) {
        $remoteFolderExists = true;
    }
} catch (Error $e) {
    // Папка не существует на сервере
    $remoteFolderExists = false;
}

if (!$remoteFolderExists) {
    // Создаем папку на Яндекс.Диске
    try {
        $client->request('MKCOL', '/'.$webdav_folder);
        echo "Папка '$webdav_folder' успешно создана на Яндекс.Диске.\n";
    } catch (Sabre\HTTP\ClientHttpException $e) {
        echo "Ошибка при создании папки '$webdav_folder' на Яндекс.Диске: ".$e->getMessage()."\n";
    }
}

// Папка, в которой хранятся бекапы
$backupFolder = 'backups';

// Маска для поиска файлов бекапа
$fileMask = $_ENV['FILE_PREFIX'].date('Y-m-d').'_*';

// Подключение к базе данных SQLite
$db = new SQLiteConnection();
$db->createTable(); // Создаем таблицу, если она не существует

// Получаем список файлов в папке backups
$localFiles = glob($backupFolder . '/' . $fileMask);

if (!empty($localFiles)) {
    foreach ($localFiles as $localFile) {
        // Имя файла без пути
        $filename = basename($localFile);

        // Проверяем существование файла в базе данных
        if (!$db->fileExists($filename)) {
            // Отправляем файл на Яндекс.Диск
            try {
                $client->request('PUT', '/'.$webdav_folder.'/'.$filename, file_get_contents($localFile));
                echo "Файл '$filename' успешно отправлен на Яндекс.Диск." . PHP_EOL;

                // Записываем информацию о файле в базу данных
                $sent_date = date('Y-m-d H:i:s');
                $db->insertFile($filename, $sent_date);
            } catch (Sabre\HTTP\ClientHttpException $e) {
                echo "Ошибка при отправке файла '$filename' на Яндекс.Диск: ".$e->getMessage()."" . PHP_EOL;
            }
        } else {
            echo "Файл '$filename' уже отправлен на Яндекс.Диск, пропускаем." . PHP_EOL;
        }
    }
} else {
    echo "Файлы с маской '$fileMask' не найдены в папке '$backupFolder'.\n";
}

// Закрываем соединение с базой данных
$db->close();
