<?php

class SQLite {
    private $db;

    public function __construct() {
        $this->db = $this->connect();

        $this->init(); // Создадим таблицы, если их нет
    }

    private function connect(): SQLite3
    {
        $dbPath = 'sqlite/db_bsb.db'; // Путь к базе данных SQLite
        return new SQLite3($dbPath);
    }

    public function close(): void
    {
        if ($this->db) {
            $this->db->close();
        }
    }

    public function init(): void
    {
        // Создание таблицы для хранения файлов, загруженных файлов
        $query = "CREATE TABLE IF NOT EXISTS sent_files (filename TEXT, sent_date DATETIME)";
        $this->db->exec($query);
        // Создание таблицы для хранения файлов, поставленных на загрузку
//        $query = "CREATE TABLE IF NOT EXISTS deployed_files (
//                        id INTEGER PRIMARY KEY,
//                        filename TEXT,
//                        deploy_time DATETIME,
//                        pid INTEGER
//                    );";
//        $this->db->exec($query);
    }

    public function insertFile($filename, $sent_date): void
    {
        $query = "INSERT INTO sent_files (filename, sent_date) VALUES (:filename, :sent_date)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename, SQLITE3_TEXT);
        $stmt->bindParam(':sent_date', $sent_date, SQLITE3_TEXT);
        $stmt->execute();
    }

    // Метод для проверки существования записи об отправке
    public function fileExists($filename): bool
    {
        $query = "SELECT EXISTS(SELECT 1 FROM sent_files WHERE filename = :filename) as file_exists";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename, SQLITE3_TEXT);

        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row['file_exists'] == 1;
    }

    // Метод получения старой записи
    public function getOldFile($maximum_storage_day): ?string
    {
        $dateThreshold = date('Y-m-d', strtotime('-' . $maximum_storage_day . ' days'));

        $query = "SELECT filename FROM sent_files WHERE sent_date < :date LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':date', $dateThreshold, SQLITE3_TEXT);
        $result = $stmt->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return $row['filename'];
        } else {
            return null;
        }
    }

    public function markFileAsDeleted($filename): void
    {
        $query = "DELETE FROM sent_files WHERE filename = :filename";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function markFileAsDownloadable($filename): void
    {
        $query = "INSERT INTO deployed_files (filename, deploy_time) VALUES (:filename, DATETIME('now'))";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function checkFileAsDownloadable($filename, $max_time) {
        // Получение текущего времени
        $currentTime = time();

        // Подготовка запроса
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM deployed_files WHERE filename = :filename AND (:currentTime - strftime("%s", deploy_time)) <= :max_time');

        // Привязка параметров и выполнение запроса
        $stmt->bindValue(':filename', $filename, SQLITE3_TEXT);
        $stmt->bindValue(':currentTime', $currentTime, SQLITE3_INTEGER);
        $stmt->bindValue(':numSeconds', $max_time, SQLITE3_INTEGER);
        $result = $stmt->execute();

        // Извлечение результата
        $count = $result->fetchArray(SQLITE3_NUM)[0];

        // Возвращение true, если запись существует, в противном случае - false
        return ($count > 0);
    }

    public function unMarkFileAsDownloadable($filename): void
    {
        $query = "DELETE FROM deployed_files WHERE filename = :filename";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename, SQLITE3_TEXT);
        $stmt->execute();
    }
}
