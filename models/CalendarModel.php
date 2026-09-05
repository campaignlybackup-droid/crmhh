<?php

class CalendarModel
{
    /** Events visible to $userId for the given month: their own events + tasks/deadlines assigned to them. */
    public static function eventsForMonth(int $userId, int $year, int $month): array
    {
        $events = Database::all(
            'SELECT * FROM calendar_events WHERE user_id = ? AND start_datetime BETWEEN ? AND ?',
            [$userId, "$year-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime("$year-$month-01"))]
        );

        $taskWhere = "assigned_user_id = ? AND deadline IS NOT NULL AND deleted_at IS NULL AND deadline BETWEEN ? AND ?";
        $tasks = Database::all(
            "SELECT id, title, deadline AS start_datetime, task_code FROM tasks WHERE $taskWhere",
            [$userId, "$year-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime("$year-$month-01"))]
        );
        foreach ($tasks as $t) {
            $events[] = [
                'id' => 'task-' . $t['id'],
                'title' => 'Deadline: ' . $t['title'],
                'event_type' => 'deadline',
                'start_datetime' => $t['start_datetime'],
                'end_datetime' => null,
                'related_type' => 'task',
                'related_id' => $t['id'],
            ];
        }
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
