<?php
/**
 * Notification Model
 */

require_once __DIR__ . '/BaseModel.php';

class Notification extends BaseModel
{
    protected string $table = 'notifications';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'recipient_id', 'recipient_type', 'title', 'body',
        'notification_type', 'related_entity_type', 'related_entity_id',
        'is_read', 'created_at',
    ];

    // ─── Send ────────────────────────────────────────────────────────

    /**
     * Send a single notification.
     *
     * @param  int         $recipientId
     * @param  string      $recipientType  'user', 'student', 'parent'
     * @param  string      $title
     * @param  string      $body
     * @param  string      $type           e.g. 'info', 'warning', 'approval', 'payment'
     * @param  array|null  $relatedEntity  ['type' => string, 'id' => int]
     * @return int  Notification ID
     */
    public function send(
        int $recipientId,
        string $recipientType,
        string $title,
        string $body,
        string $type,
        ?array $relatedEntity = null
    ): int {
        $data = [
            'recipient_id'   => $recipientId,
            'recipient_type' => $recipientType,
            'title'          => $title,
            'body'           => $body,
            'notification_type' => $type,
            'is_read'        => 0,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        if ($relatedEntity) {
            $data['related_entity_type'] = $relatedEntity['type'] ?? null;
            $data['related_entity_id']   = $relatedEntity['id'] ?? null;
        }

        return $this->create($data);
    }

    /**
     * Send the same notification to multiple recipients.
     *
     * @param  array  $recipients  Array of ['id' => int, 'type' => string]
     * @param  string $title
     * @param  string $body
     * @param  string $type
     * @return int    Number of notifications sent
     */
    public function sendBulk(array $recipients, string $title, string $body, string $type): int
    {
        $sql = "INSERT INTO {$this->table}
                (recipient_id, recipient_type, title, body, notification_type, is_read, created_at)
                VALUES (:rid, :rtype, :title, :body, :type, 0, NOW())";

        $count = 0;
        foreach ($recipients as $r) {
            $this->query($sql, [
                ':rid'   => $r['id'],
                ':rtype' => $r['type'],
                ':title' => $title,
                ':body'  => $body,
                ':type'  => $type,
            ]);
            $count++;
        }
        return $count;
    }

    // ─── Read ────────────────────────────────────────────────────────

    /**
     * Get notifications for a user.
     *
     * @param  int    $userId
     * @param  string $userType    'user', 'student', 'parent'
     * @param  bool   $unreadOnly
     */
    public function getForUser(int $userId, string $userType, bool $unreadOnly = false): array
    {
        $params = [':uid' => $userId, ':utype' => $userType];
        $readFilter = '';
        if ($unreadOnly) {
            $readFilter = 'AND is_read = 0';
        }

        $sql = "SELECT * FROM {$this->table}
                WHERE recipient_id = :uid AND recipient_type = :utype
                {$readFilter}
                ORDER BY created_at DESC";
        return $this->query($sql, $params)->fetchAll();
    }

    // ─── Mark read ───────────────────────────────────────────────────

    public function markRead(int $id): bool
    {
        $sql = "UPDATE {$this->table}
                SET is_read = 1, read_at = NOW()
                WHERE id = :id AND is_read = 0";
        return $this->query($sql, [':id' => $id])->rowCount() > 0;
    }

    public function markAllRead(int $userId, string $userType): int
    {
        $sql = "UPDATE {$this->table}
                SET is_read = 1, read_at = NOW()
                WHERE recipient_id = :uid AND recipient_type = :utype AND is_read = 0";
        return $this->query($sql, [':uid' => $userId, ':utype' => $userType])->rowCount();
    }

    // ─── Count ───────────────────────────────────────────────────────

    public function getUnreadCount(int $userId, string $userType): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table}
                WHERE recipient_id = :uid AND recipient_type = :utype AND is_read = 0";
        return (int) $this->query($sql, [':uid' => $userId, ':utype' => $userType])->fetch()['cnt'];
    }
}
