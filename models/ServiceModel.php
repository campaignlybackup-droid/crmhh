<?php

class ServiceModel
{
    public static function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM services';
        if ($activeOnly) $sql .= ' WHERE is_active = 1';
        $services = Database::all($sql . ' ORDER BY name');
        
        $subcategories = Database::all('SELECT * FROM service_subcategories ORDER BY name');
        $subsByService = [];
        foreach ($subcategories as $sub) {
            $subsByService[$sub['service_id']][] = $sub;
        }
        foreach ($services as &$s) {
            $s['subcategories'] = $subsByService[$s['id']] ?? [];
        }
        return $services;
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

    public static function addSubcategory(int $serviceId, string $name): int
    {
        Database::run('INSERT INTO service_subcategories (service_id, name) VALUES (?, ?)', [$serviceId, $name]);
        return (int)Database::lastInsertId();
    }

    public static function removeSubcategory(int $subcategoryId): void
    {
        Database::run('DELETE FROM service_subcategories WHERE id = ?', [$subcategoryId]);
    }
}
