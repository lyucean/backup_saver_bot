<?php

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

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

// Путь к папке бекапов на Яндекс.Диске
$webdav_folder = $_ENV['WEBDAV_FOLDER'];

// Папка, в которой хранятся бекапы
$backupFolder = 'backups';

// Маска для поиска файлов бекапа
$fileMask = $_ENV['FILE_PREFIX'].date('Y-m-d').'_*';

// Получаем список файлов в папке backups
$localFiles = glob($backupFolder . '/' . $fileMask);

if (!empty($localFiles)) {
    foreach ($localFiles as $localFile) {
        // Имя файла без пути
        $filename = basename($localFile);

        // Путь к файлу на Яндекс.Диске
        $remoteFilePath = '/'.$webdav_folder.'/'.$filename;

        // Проверяем существование файла на сервере
        $remoteFileExists = false;
        try {
            $response = $client->request('HEAD', $remoteFilePath);
            if ($response['statusCode'] === 200) {
                $remoteFileExists = true;
            }
        } catch (Sabre\HTTP\ClientHttpException $e) {
            // Файл не существует на сервере, можно отправлять
            $remoteFileExists = false;
        }

        if (!$remoteFileExists) {
            // Отправляем файл на Яндекс.Диск
            try {
                $client->request('PUT', $remoteFilePath, file_get_contents($localFile));
                echo "Файл '$filename' успешно отправлен на Яндекс.Диск.\n";
            } catch (Sabre\HTTP\ClientHttpException $e) {
                echo "Ошибка при отправке файла '$filename' на Яндекс.Диск: ".$e->getMessage()."\n";
            }
        } else {
            echo "Файл '$filename' уже существует на Яндекс.Диске, пропускаем.\n";
        }
    }
} else {
    echo "Файлы с маской '$fileMask' не найдены в папке '$backupFolder'.\n";
}
