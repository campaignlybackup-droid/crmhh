<?php

class UserModel
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [strtolower(trim($email))]);
    }

    public static function paginate(int $page, int $perPage, string $search = '', string $status = ''): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR employee_code LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int)Database::scalar("SELECT COUNT(*) FROM users WHERE $whereSql", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT * FROM users WHERE $whereSql ORDER BY name ASC LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        return [$rows, $p];
    }

    public static function all(): array
    {
        return Database::all('SELECT * FROM users WHERE deleted_at IS NULL ORDER BY name ASC');
    }

    public static function activeSelectList(): array
    {
        return Database::all("SELECT id, name, employee_code FROM users WHERE deleted_at IS NULL AND status='active' ORDER BY name ASC");
    }

    public static function rolesFor(int $userId): array
    {
        return Database::all(
            'SELECT r.* FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.name',
            [$userId]
        );
    }

    public static function roleSlugsFor(int $userId): array
    {
        return array_column(self::rolesFor($userId), 'slug');
    }

    public static function teamsFor(int $userId): array
    {
        return Database::all(
            'SELECT t.* FROM teams t JOIN team_members tm ON tm.team_id = t.id WHERE tm.user_id = ? AND t.deleted_at IS NULL',
            [$userId]
        );
    }

    public static function managedTeamsFor(int $userId): array
    {
        return Database::all(
            'SELECT t.* FROM teams t JOIN team_managers tmg ON tmg.team_id = t.id WHERE tmg.user_id = ? AND t.deleted_at IS NULL',
            [$userId]
        );
    }

    public static function create(array $data, array $roleIds): int
    {
        Database::beginTransaction();
        try {
            $code = next_code('users', 'employee_code', 'EMP', 4);
            Database::run(
                'INSERT INTO users (employee_code, name, email, phone, password_hash, is_founder, manager_id, status, must_change_password, created_at)
                 VALUES (?,?,?,?,?,?,?,?,1,NOW())',
                [
                    $code,
                    $data['name'],
                    strtolower(trim($data['email'])),
                    $data['phone'] ?: null,
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['is_founder'] ?? 0,
                    $data['manager_id'] ?: null,
                    'active',
                ]
            );
            $id = (int)Database::lastInsertId();
            foreach ($roleIds as $rid) {
                Database::run('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, (int)$rid]);
            }
            AuditLog::record('create', 'user', $id, null, $data['name']);
            Database::commit();
            return $id;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data, array $roleIds): void
    {
        Database::beginTransaction();
        try {
            $before = self::find($id);
            Database::run(
                'UPDATE users SET name=?, email=?, phone=?, manager_id=? WHERE id=?',
                [$data['name'], strtolower(trim($data['email'])), $data['phone'] ?: null, $data['manager_id'] ?: null, $id]
            );
            Database::run('DELETE FROM user_roles WHERE user_id = ?', [$id]);
            foreach ($roleIds as $rid) {
                Database::run('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, (int)$rid]);
            }
            AuditLog::record('update', 'user', $id, $before['name'] ?? null, $data['name']);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
        AuditLog::record($status === 'active' ? 'enable' : 'disable', 'user', $id);
    }

    public static function resetPassword(int $id, string $newPassword): void
    {
        Database::run('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        AuditLog::record('reset_password', 'user', $id);
    }

    public static function changeOwnPassword(int $id, string $newPassword): void
    {
        Database::run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        AuditLog::record('change_password', 'user', $id);
    }
}
