<?php

class RoleModel
{
    public static function all(): array
    {
        return Database::all('SELECT * FROM roles ORDER BY is_system DESC, name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM roles WHERE id = ?', [$id]);
    }

    public static function permissionsFor(int $roleId): array
    {
        return Database::all(
            'SELECT p.* FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?',
            [$roleId]
        );
    }

    public static function permissionSlugsFor(int $roleId): array
    {
        return array_column(self::permissionsFor($roleId), 'slug');
    }

    public static function allPermissions(): array
    {
        return Database::all('SELECT * FROM permissions ORDER BY `group`, name');
    }

    public static function create(string $name, string $slug, ?string $description, array $permissionIds): int
    {
        Database::beginTransaction();
        try {
            Database::run('INSERT INTO roles (name, slug, description, is_system, created_at) VALUES (?,?,?,0,NOW())', [$name, $slug, $description]);
            $id = (int)Database::lastInsertId();
            foreach ($permissionIds as $pid) {
                Database::run('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)', [$id, (int)$pid]);
            }
            AuditLog::record('create', 'role', $id, null, $name);
            Database::commit();
            return $id;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function update(int $id, string $name, ?string $description, array $permissionIds): void
    {
        Database::beginTransaction();
        try {
            Database::run('UPDATE roles SET name=?, description=? WHERE id=?', [$name, $description, $id]);
            Database::run('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
            foreach ($permissionIds as $pid) {
                Database::run('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)', [$id, (int)$pid]);
            }
            AuditLog::record('update_permissions', 'role', $id);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool
    {
        $role = self::find($id);
        if (!$role || (int)$role['is_system'] === 1) return false;
        Database::run('DELETE FROM roles WHERE id = ?', [$id]);
        AuditLog::record('delete', 'role', $id, $role['name']);
        return true;
    }

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        return $slug !== '' ? $slug : 'role-' . time();
    }
}
