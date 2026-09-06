<?php

class ApprovalModel
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT a.*, u.name AS sender_name, r.name AS reviewer_name
             FROM approvals a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN users r ON r.id = a.reviewer_id
             WHERE a.id = ?',
            [$id]
        );
    }

    public static function create(array $data): int
    {
        Database::run(
            'INSERT INTO approvals (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$data['user_id'], $data['title'], $data['description'] ?: null, 'pending']
        );
        return (int)Database::lastInsertId();
    }

    public static function updateStatus(int $id, string $status, int $reviewerId, ?string $notes): void
    {
        Database::run(
            'UPDATE approvals SET status = ?, reviewer_id = ?, reviewer_notes = ? WHERE id = ?',
            [$status, $reviewerId, $notes, $id]
        );
    }

    public static function paginate(int $page, int $perPage, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        
        $where = "1=1";
        $params = [];

        // Visibility rules
        if (!Auth::hasRole('founder') && !Auth::hasRole('manager')) {
            // Normal team members only see their own requests
            $where = "a.user_id = ?";
            $params[] = $userId;
        }

        $total = (int)Database::scalar("SELECT COUNT(*) FROM approvals a WHERE $where", $params);
        $p = paginate_params($total, $page, $perPage);
        $rows = Database::all(
            "SELECT a.*, u.name AS sender_name, r.name AS reviewer_name 
             FROM approvals a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN users r ON r.id = a.reviewer_id
             WHERE $where
             ORDER BY a.created_at DESC
             LIMIT {$p['perPage']} OFFSET {$p['offset']}",
            $params
        );
        return [$rows, $p];
    }
}
