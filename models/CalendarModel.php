<?php

class CalendarModel
{
    public static function eventsForMonth($userId, int $year, int $month): array
    {
        $startDate = "$year-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00';
        $endDate = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));
        $isFounder = Auth::hasRole('founder');
        $isManager = Auth::hasRole('manager');
        
        $events = [];
        
        // 1. Events Query
        $evWhere = "start_datetime BETWEEN ? AND ?";
        $evParams = [$startDate, $endDate];
        if ($userId !== 'all') { $evWhere .= " AND user_id = ?"; $evParams[] = $userId; }
        if (!$isFounder && $isManager) { $evWhere .= " AND user_id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'founder')"; }
        
        $dbEvents = Database::all("SELECT * FROM calendar_events WHERE $evWhere", $evParams);
        foreach ($dbEvents as $e) { $events[] = $e; }

        // 2. Tasks Query
        $taskWhere = "deadline IS NOT NULL AND deleted_at IS NULL AND deadline BETWEEN ? AND ?";
        $taskParams = [$startDate, $endDate];
        if ($userId !== 'all') { $taskWhere .= " AND assigned_user_id = ?"; $taskParams[] = $userId; }
        if (!$isFounder && $isManager) { $taskWhere .= " AND assigned_user_id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'founder')"; }
        
        $tasks = Database::all("SELECT id, title, deadline AS start_datetime, task_code, description FROM tasks WHERE $taskWhere", $taskParams);
        foreach ($tasks as $t) {
            $events[] = [
                'id' => 'task-' . $t['id'],
                'title' => 'Task: ' . $t['title'],
                'description' => $t['description'],
                'event_type' => 'deadline',
                'start_datetime' => $t['start_datetime'],
                'end_datetime' => null,
                'related_type' => 'task',
                'related_id' => $t['id'],
                'location' => null
            ];
        }
        
        // 3. Leads Follow-ups Query
        $leadWhere = "next_followup_date IS NOT NULL AND deleted_at IS NULL AND next_followup_date BETWEEN ? AND ?";
        $leadParams = [date('Y-m-d', strtotime($startDate)), date('Y-m-d', strtotime($endDate))];
        if ($userId !== 'all') { $leadWhere .= " AND assigned_user_id = ?"; $leadParams[] = $userId; }
        if (!$isFounder && $isManager) { $leadWhere .= " AND assigned_user_id NOT IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'founder')"; }
        
        $leads = Database::all("SELECT id, name, next_followup_date, notes FROM leads WHERE $leadWhere", $leadParams);
        foreach ($leads as $l) {
            $events[] = [
                'id' => 'lead-' . $l['id'],
                'title' => 'Lead Follow-up: ' . $l['name'],
                'description' => $l['notes'],
                'event_type' => 'followup',
                'start_datetime' => $l['next_followup_date'] . ' 09:00:00', // Default to 9am for sorting
                'end_datetime' => null,
                'related_type' => 'lead',
                'related_id' => $l['id'],
                'location' => null
            ];
        }

        // Sort all chronologically
        usort($events, function($a, $b) {
            return strtotime($a['start_datetime']) <=> strtotime($b['start_datetime']);
        });

        return $events;
    }

    public static function create(array $data): int
    {
        Database::run(
            'INSERT INTO calendar_events (user_id, title, description, event_type, related_type, related_id, start_datetime, end_datetime, location, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $data['user_id'], $data['title'], $data['description'] ?: null, $data['event_type'] ?: 'event',
                $data['related_type'] ?? null, $data['related_id'] ?? null, $data['start_datetime'], $data['end_datetime'] ?: null,
                $data['location'] ?? null, Auth::id(),
            ]
        );
        return (int)Database::lastInsertId();
    }

    public static function delete(int $id, int $userId): void
    {
        Database::run('DELETE FROM calendar_events WHERE id = ? AND (user_id = ? OR created_by = ?)', [$id, $userId, $userId]);
    }

    public static function founderAvailabilityForMonth(int $year, int $month): array
    {
        return Database::all(
            'SELECT fa.*, u.name AS founder_name FROM founder_availability fa JOIN users u ON u.id = fa.founder_user_id
             WHERE YEAR(date) = ? AND MONTH(date) = ? ORDER BY date',
            [$year, $month]
        );
    }

    public static function setAvailability(int $founderId, string $date, string $status, ?string $note): void
    {
        Database::run(
            'INSERT INTO founder_availability (founder_user_id, date, status, note, created_at) VALUES (?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)',
            [$founderId, $date, $status, $note ?: null]
        );
    }
}
