<?php

require_once('vendor/autoload.php');
require_once('db.php');
require_once('log.php');

// ------------- config ----------------
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
$dotenv->required('PERIOD_SECONDS_RUN')->notEmpty();

// Копим логи ошибок в Sentry
if (!empty($_ENV['SENTRY_DNS'])) {
    \Sentry\init([
      'dsn' => $_ENV['SENTRY_DNS'],
      'release' => date("Y-m-d_H.i", filectime(__FILE__)), //тест релиза
      'environment' => $_ENV['ENVIRONMENT'],
      'traces_sample_rate' => 0.2,
    ]);
}
ini_set('memory_limit', '256M');
date_default_timezone_set('Europe/Moscow'); // московский регион

// Подключим класс логов
$logger = Logs::getInstance();
$logger->info('Запуск бота BSB', [
    "environment" => $_ENV['ENVIRONMENT'],
    "release" => $_ENV['RELEASE_DATE'],
]);

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
$periodRun= $_ENV['PERIOD_SECONDS_RUN']; // Время задержки между выполнениями


// Бесконечный цикл, который будет вызывать основной файл скрипта
while (true) {
    // отправим на сервер новые
    send_file();

    // очистим старые файлы
    clean_file();

    // постучимся, что мы живы
    heartbeat();

    sleep($periodRun);
}

function send_file(): void
{
    global $client, $db, $logger, $backupFolder, $fileMask, $webdav_folder;
    // Получаем список файлов в папке backups
    $localFiles = glob($backupFolder . '/' . $fileMask);

    if (!empty($localFiles)) {
        $logger->info("Файлы найдены");

        foreach ($localFiles as $localFile) {
            $filename = basename($localFile); // Имя файла без пути

            if ($db->fileExists($filename)) {
                $logger->info("Файл '$filename' уже есть в БД, пропускаем.");
                continue; // пропускаем текущую итерацию
            }

            $logger->info("Проверим что файла нет '$filename' на Яндекс Диск");
            try {
                // Попытка выполнить запрос HEAD для проверки файла
                $response = $client->request('HEAD', '/'.$webdav_folder.'/'.$filename);

                // Проверяем статус ответа, чтобы увидеть, существует ли файл
                if ($response['statusCode'] == 200) {
                    $logger->warning("Файл $filename уже существует на Яндекс Диск");

                    // Записываем информацию о файле в базу данных
                    $formattedCreationTime = date('Y-m-d H:i:s', filectime($localFile)); // Получение времени создания файла
                    $db->insertFile($filename, $formattedCreationTime);

                    continue; // пропускаем текущую итерацию
                } elseif ($response['statusCode'] == 404) {
                    $logger->info("Файл $filename не найден на Яндекс Диск, можно начать загрузку.");
                } else {
                    // Другой ответ, возможно, требуется обработка ошибок
                    $logger->error("Не удалось проверить файл на Яндекс Диск: HTTP статус ".$response['statusCode']);
                }
            } catch (Exception $e) {
                $logger->error("Ошибка при проверки файла '$filename' на Яндекс Диске: ".$e->getMessage());
            }

            $logger->info("Отправляем '$filename' на Яндекс Диск");

            try {
                $fp = fopen($localFile, 'r');
                $client->request('PUT', '/'.$webdav_folder.'/'.$filename, $fp);
                fclose($fp);

                $logger->info("Файл '$filename' успешно отправлен на Яндекс Диск.");

                // Записываем информацию о файле в базу данных
                $formattedCreationTime = date('Y-m-d H:i:s', filectime($localFile)); // Получение времени создания файла
                $db->insertFile($filename, $formattedCreationTime);

                break; // Отправка данных происходит очень долго, поэтому следующий отправим в новом цикле.
            } catch (Exception $e) {
                $logger->error("Ошибка при отправке файла '$filename' на Яндекс Диск: ".$e->getMessage());
            }
        }
    } else {
        $logger->warning("Файлы с маской '$fileMask' не найдены в папке '$backupFolder'.");
    }
}

function clean_file() {

    global $client, $db, $logger, $backupFolder, $webdav_folder, $maximum_storage_day;

    // получим старую запись под удаление
    $filename = $db->getOldFile($maximum_storage_day);
    // Удаляем файлы, старше 7 дней с сервера, с sqlite и с Яндекс Диска
    if (!empty($filename)) {

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
}

function heartbeat() {
    if (!empty($_ENV['HEARTBEAT_TOKEN'])) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://uptime.betterstack.com/api/v1/heartbeat/' . $_ENV['HEARTBEAT_TOKEN']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_exec($ch);
        curl_close($ch);
    }
}


