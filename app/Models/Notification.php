<?php
/**
 * Notification Model — In-app System Message Inbox
 * Document 1 & Kavindu Role Guide Specification:
 * - System notifications list
 * - Read/unread status
 * - Filters by role/date/type
 * - Notification details view
 */

require_once __DIR__ . '/BaseModel.php';

class Notification extends BaseModel
{
    protected string $table = 'notifications';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'recipient_id', 'recipient_type', 'title', 'body',
        'notification_type', 'is_read', 'related_entity_type',
        'related_entity_id', 'created_at',
    ];

    /**
     * Send a single notification.
     */
    public function send(
        int $recipientId,
        string $recipientType,
        string $title,
        string $body,
        string $type = 'info',
        ?array $relatedEntity = null
    ): int {
        $sql = "INSERT INTO {$this->table} 
                (recipient_id, recipient_type, title, body, notification_type, is_read, related_entity_type, related_entity_id, created_at)
                VALUES 
                (:rid, :rtype, :title, :body, :ntype, 0, :retype, :reid, NOW())";

        $this->query($sql, [
            ':rid'    => $recipientId,
            ':rtype'  => $recipientType,
            ':title'  => $title,
            ':body'   => $body,
            ':ntype'  => $type,
            ':retype' => $relatedEntity['type'] ?? null,
            ':reid'   => $relatedEntity['id'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Send bulk notifications to multiple recipients.
     */
    public function sendBulk(array $recipients, string $title, string $body, string $type = 'info'): int
    {
        $count = 0;
        foreach ($recipients as $r) {
            $this->send((int) $r['id'], $r['type'] ?? 'user', $title, $body, $type);
            $count++;
        }
        return $count;
    }

    /**
     * Get filtered inbox for a user
     */
    public function getFilteredInbox(int $recipientId, string $recipientType = 'user', array $filters = []): array
    {
        $params = [':rid' => $recipientId, ':rtype' => $recipientType];
        $where = ['(n.recipient_id = :rid AND n.recipient_type = :rtype)'];

        if (!empty($filters['type'])) {
            $where[] = 'n.notification_type = :ntype';
            $params[':ntype'] = $filters['type'];
        }

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $where[] = 'n.is_read = :read';
            $params[':read'] = (int) $filters['is_read'];
        }

        if (!empty($filters['search'])) {
            // One placeholder per use -- see ClassModel::getGridList.
            $where[] = '(n.title LIKE :search1 OR n.body LIKE :search2)';
            $needle = '%' . trim($filters['search']) . '%';
            $params[':search1'] = $needle;
            $params[':search2'] = $needle;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT n.* FROM {$this->table} n
                WHERE {$whereClause}
                ORDER BY n.created_at DESC";

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Count unread notifications
     */
    public function getUnreadCount(int $recipientId, string $recipientType = 'user'): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table} 
                WHERE recipient_id = :rid AND recipient_type = :rtype AND is_read = 0";
        $row = $this->query($sql, [':rid' => $recipientId, ':rtype' => $recipientType])->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(int $id, int $recipientId): bool
    {
        $sql = "UPDATE {$this->table} SET is_read = 1 WHERE id = :id AND recipient_id = :rid";
        $stmt = $this->query($sql, [':id' => $id, ':rid' => $recipientId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications as read for recipient
     */
    public function markAllAsRead(int $recipientId, string $recipientType = 'user'): bool
    {
        $sql = "UPDATE {$this->table} SET is_read = 1 WHERE recipient_id = :rid AND recipient_type = :rtype";
        $stmt = $this->query($sql, [':rid' => $recipientId, ':rtype' => $recipientType]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Broadcast notification to all users or specific role
     */
    public function broadcast(string $title, string $body, string $type = 'system', string $targetRole = 'all'): int
    {
        $where = ($targetRole === 'all') ? '' : "WHERE role = '{$targetRole}'";
        $sql = "SELECT id, 'user' AS type FROM users {$where}";
        $recipients = $this->query($sql)->fetchAll();

        return $this->sendBulk($recipients, $title, $body, $type);
    }

    /**
     * Broadcast a notification to every actively-enrolled student of one
     * class (and, optionally, their parents too), so a branch admin or
     * the super admin can reach "the students of the relevant class"
     * directly instead of only every staff account site-wide.
     */
    public function broadcastToClass(int $classId, string $title, string $body, string $type = 'system', bool $alsoParents = false): int
    {
        $stmt = $this->query(
            "SELECT DISTINCT s.id, 'student' AS type
               FROM students s
               JOIN enrollments e ON e.student_id = s.id
              WHERE e.class_id = :cid AND e.enrollment_status = 'active' AND s.status = 'active'",
            [':cid' => $classId]
        );
        $recipients = $stmt->fetchAll();

        if ($alsoParents) {
            $pstmt = $this->query(
                "SELECT DISTINCT p.id, 'parent' AS type
                   FROM parents p
                   JOIN students s ON s.id = p.student_id
                   JOIN enrollments e ON e.student_id = s.id
                  WHERE e.class_id = :cid AND e.enrollment_status = 'active'
                        AND s.status = 'active' AND p.status = 'active'",
                [':cid' => $classId]
            );
            $recipients = array_merge($recipients, $pstmt->fetchAll());
        }

        return $this->sendBulk($recipients, $title, $body, $type);
    }
}
