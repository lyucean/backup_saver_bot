<?php

require_once('vendor/autoload.php');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dotenv->required('ENVIRONMENT')->notEmpty();

echo $_ENV['ENVIRONMENT'];

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

// Имя файла с текущей датой и временем
$filename = 'report-'.date('Y-m-d_H:i:s').'.txt';

// Путь к файлу на Яндекс.Диске
$filePath = '/BackupSaverBot/'.$filename;

// Создаем пустой файл на Яндекс.Диске
try {
    $client->request('PUT', $filePath);
    echo "Файл '$filename' успешно создан на Яндекс.Диске.\n";
} catch (Sabre\HTTP\ClientHttpException $e) {
    echo "Ошибка при создании файла: ".$e->getMessage()."\n";
}