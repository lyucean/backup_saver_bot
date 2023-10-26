<?php

class SQLiteConnection {
    private $db;

    public function __construct() {
        $this->db = $this->connect();
    }

    private function connect(): SQLite3
    {
        $dbPath = 'sqlite/db_bsb.db'; // Путь к базе данных SQLite
        $db = new SQLite3($dbPath);
        return $db;
    }

    public function close(): void
    {
        if ($this->db) {
            $this->db->close();
        }
    }

    public function createTable(): void
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
        $query = "SELECT COUNT(*) FROM sent_files WHERE filename = :filename";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filename', $filename, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray(SQLITE3_NUM);
        return $result[0] > 0;
    }
}
