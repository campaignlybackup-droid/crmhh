<?php

namespace App\Models;

use PDO;

abstract class Model {
    protected $pdo;
    protected $table;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Find a record by ID.
     */
    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get all records.
     */
    public function all() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    /**
     * Create a new record.
     */
    public function create(array $data) {
        $keys = array_keys($data);
        $fields = implode(',', $keys);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Update a record.
     */
    public function update($id, array $data) {
        $setClauses = [];
        foreach (array_keys($data) as $key) {
            $setClauses[] = "{$key} = ?";
        }
        $setString = implode(', ', $setClauses);
        
        $sql = "UPDATE {$this->table} SET {$setString} WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        $values = array_values($data);
        $values[] = $id;
        
        return $stmt->execute($values);
    }

    /**
     * Delete a record.
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
