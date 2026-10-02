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
            $where[] = '(n.title LIKE :search OR n.body LIKE :search)';
            $params[':search'] = '%' . trim($filters['search']) . '%';
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
}
