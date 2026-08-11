<?php

class DatabaseManager
{
    private ?PDO $pdo = null;
    private string $dsn = '';
    private string $user = '';
    private string $pass = '';

    public function __construct(string $host, string $dbName, string $user, string $pass)
    {
        $this->dsn = "mysql:host={$host};dbname={$dbName};charset=utf8";
        $this->user = $user;
        $this->pass = $pass;
    }

    public function connect(): PDO
    {
        if ($this->pdo === null) {
            // Without an explicit connect timeout, a MySQL connection that's
            // slow to respond (service restart, transient network stall) can
            // block for the entire remaining request lifetime, surfacing only
            // as an opaque "Maximum execution time exceeded" fatal wherever
            // the script happened to be when the 120s script-level cap hit —
            // not at the actual point of failure. Fail fast instead, so a
            // real connectivity problem throws a catchable PDOException here.
            $this->pdo = new PDO($this->dsn, $this->user, $this->pass, array(
                PDO::ATTR_TIMEOUT => 10,
            ));
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $this->pdo;
    }

    public function testConnection(): array
    {
        try {
            $pdo = new PDO($this->dsn, $this->user, $this->pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo = $pdo;
            return ['success' => true, 'message' => ''];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function odmsysTableExists(string $prefix): bool
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->query("SHOW TABLES LIKE '{$prefix}odmsys'");
            return $stmt->fetch(PDO::FETCH_NUM) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getDbVersion(string $prefix): ?string
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare("SELECT sys_value FROM {$prefix}odmsys WHERE sys_name = 'version'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_COLUMN);
            return $row !== false ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function createDatabase(): void
    {
        $pdo = $this->connect();
        $dbName = $this->getDbNameFromDsn();
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
    }

    public function dropAllTables(string $prefix): void
    {
        $pdo = $this->connect();
        $stmt = $pdo->query("SHOW TABLES LIKE '{$prefix}%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    public function getDriverName(): string
    {
        return 'mysql';
    }

    private function getDbNameFromDsn(): string
    {
        if (preg_match('/dbname=([^;]+)/', $this->dsn, $matches)) {
            return $matches[1];
        }
        return '';
    }
}