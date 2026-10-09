<?php

class CalendarModel
{
    public static function eventsForMonth($userId, int $year, int $month): array
    {
        $startDate = "$year-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00';
        $endDate = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));
        $currentUserId = Auth::id() ?: 1;
        $isFounder = Auth::hasRole('founder') || Auth::isFounder();
        $isManager = Auth::hasRole('manager') || !empty(UserModel::managedTeamsFor($currentUserId));
        
        // Determine allowed target user IDs to completely prevent cross-team leakage
        $targetUserIds = [];
        if ($userId === 'all') {
            if ($isFounder) {
                $targetUserIds = null; // Unrestricted for founder
            } elseif ($isManager) {
                $targetUserIds = Permission::managedUserIds($currentUserId);
            } else {
                $targetUserIds = [$currentUserId];
            }
        } else {
            $reqId = (int)$userId;
            if ($isFounder) {
                $targetUserIds = [$reqId];
            } elseif ($isManager) {
                $managed = Permission::managedUserIds($currentUserId);
                $targetUserIds = in_array($reqId, $managed, true) ? [$reqId] : [$currentUserId];
            } else {
                $targetUserIds = [$currentUserId];
            }
        }

        $events = [];
        
        // 1. Events Query
        $evWhere = "start_datetime BETWEEN ? AND ?";
        $evParams = [$startDate, $endDate];
        if ($targetUserIds !== null) {
            if (empty($targetUserIds)) {
                $evWhere .= " AND 0=1";
            } else {
                $ph = implode(',', array_fill(0, count($targetUserIds), '?'));
                $evWhere .= " AND user_id IN ($ph)";
                $evParams = array_merge($evParams, $targetUserIds);
            }
        }
        
        $dbEvents = Database::all("SELECT * FROM calendar_events WHERE $evWhere", $evParams);
        foreach ($dbEvents as $e) {
            $e['formatted_time'] = date('g:ia', strtotime($e['start_datetime']));
            $events[] = $e;
        }

        // 2. Tasks Query (also respecting privacy and client visibility)
        $taskWhere = "t.deadline IS NOT NULL AND t.deleted_at IS NULL AND t.deadline BETWEEN ? AND ?";
        $taskParams = [$startDate, $endDate];
        if ($targetUserIds !== null) {
            if (empty($targetUserIds)) {
                $taskWhere .= " AND 0=1";
            } else {
                $ph = implode(',', array_fill(0, count($targetUserIds), '?'));
                $taskWhere .= " AND t.assigned_user_id IN ($ph)";
                $taskParams = array_merge($taskParams, $targetUserIds);
            }
        }
        if (!$isFounder) {
            $taskWhere .= " AND (t.is_private = 0 OR t.assigned_user_id = ? OR t.assigned_by = ?)";
            $taskParams[] = $currentUserId;
            $taskParams[] = $currentUserId;
        }
        
        $tasks = Database::all("SELECT t.id, t.title, t.deadline AS start_datetime, t.task_code, t.description FROM tasks t WHERE $taskWhere", $taskParams);
        foreach ($tasks as $t) {
            $events[] = [
                'id' => 'task-' . $t['id'],
                'title' => 'Task: ' . $t['title'],
                'description' => $t['description'],
                'event_type' => 'deadline',
                'start_datetime' => $t['start_datetime'],
                'formatted_time' => date('g:ia', strtotime($t['start_datetime'])),
                'end_datetime' => null,
                'related_type' => 'task',
                'related_id' => $t['id'],
                'location' => null
            ];
        }
        
        // 3. Leads Follow-ups Query
        $leadWhere = "l.next_followup_date IS NOT NULL AND l.deleted_at IS NULL AND l.next_followup_date BETWEEN ? AND ?";
        $leadParams = [date('Y-m-d', strtotime($startDate)), date('Y-m-d', strtotime($endDate))];
        if ($targetUserIds !== null) {
            if (empty($targetUserIds)) {
                $leadWhere .= " AND 0=1";
            } else {
                $ph = implode(',', array_fill(0, count($targetUserIds), '?'));
                $leadWhere .= " AND l.assigned_user_id IN ($ph)";
                $leadParams = array_merge($leadParams, $targetUserIds);
            }
        }
        
        $leads = Database::all("SELECT l.id, l.name, l.next_followup_date, l.notes FROM leads l WHERE $leadWhere", $leadParams);
        foreach ($leads as $l) {
            $events[] = [
                'id' => 'lead-' . $l['id'],
                'title' => 'Lead Follow-up: ' . $l['name'],
                'description' => $l['notes'],
                'event_type' => 'followup',
                'start_datetime' => $l['next_followup_date'] . ' 09:00:00', // Default to 9am for sorting
                'formatted_time' => '9:00am',
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
