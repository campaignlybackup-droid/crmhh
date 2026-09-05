<?php

class Database
{
    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            $cfg = config('db');
            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                app_log('DB connection failed: ' . $e->getMessage());
                fatal_error('Unable to connect to the database. Please check the configuration or contact the administrator.');
            }
        }
        return self::$instance;
    }

    /** Run a SELECT and return all rows. */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a SELECT and return the first row, or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Run a SELECT and return a single scalar value. */
    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    /** Run an INSERT/UPDATE/DELETE, return affected row count. */
    public static function run(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }

    public static function beginTransaction(): void { self::pdo()->beginTransaction(); }
    public static function commit(): void { self::pdo()->commit(); }
    public static function rollBack(): void { if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }
}
