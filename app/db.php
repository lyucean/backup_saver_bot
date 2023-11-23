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
        $query = "CREATE TABLE IF NOT EXISTS sent_files (filename TEXT, sent_date DATETIME)";
        $this->db->exec($query);
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

    // Метод получения старых записей
    public function getOldFiles($maximum_storage_day): array
    {
        $dateThreshold = date('Y-m-d', strtotime('-' . $maximum_storage_day . ' days'));

        $query = "SELECT filename FROM sent_files WHERE sent_date < :date";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':date', $dateThreshold, SQLITE3_TEXT);
        $result = $stmt->execute();

        $files = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $files[] = $row['filename'];
        }

        return $files;
    }

    public function markFileAsDeleted($filename): void
    {
        $query = "DELETE FROM sent_files WHERE filename = :filename";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename, SQLITE3_TEXT);
        $stmt->execute();
    }
}
