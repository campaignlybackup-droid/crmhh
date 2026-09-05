<?php

class ServiceModel
{
    public static function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM services';
        if ($activeOnly) $sql .= ' WHERE is_active = 1';
        return Database::all($sql . ' ORDER BY name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM services WHERE id = ?', [$id]);
    }

    public static function create(string $name, string $unitLabel): int
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        Database::run('INSERT INTO services (name, slug, unit_label, is_active, created_at) VALUES (?,?,?,1,NOW())', [$name, $slug, $unitLabel ?: 'units']);
        $id = (int)Database::lastInsertId();
        AuditLog::record('create', 'service', $id, null, $name);
        return $id;
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE services SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }
}
