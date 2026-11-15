<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Composes WhatsApp messages and keeps the record of them.
 *
 * There is no Business API here. Messages go out through click-to-chat
 * links, which the teacher taps to open WhatsApp with the text already
 * written. That is a deliberate choice rather than a missing feature:
 * the API needs Meta approval, a verified business and a per-message
 * fee, and a one-teacher tuition class sends a few dozen reminders a
 * month from her own phone.
 *
 * What this service owns is the part that is worth owning -- the
 * wording, who each message is for, and whether anybody has actually
 * sent it. 'Sent' here means a person pressed send, not that a server
 * confirmed delivery, and the code says so wherever it matters.
 */
class WhatsAppService
{
    private PDO $db;

    /** Sri Lankan numbers, which is every number in this system. */
    private const COUNTRY_CODE = '94';

    /** Client and sending modes */
    public const MODE_WA_ME       = 'wa_me';
    public const MODE_WEB_DIRECT  = 'wa_web';
    public const MODE_DESKTOP_APP = 'wa_app';
    public const MODE_GATEWAY     = 'wa_gateway';

    public const MODES = [
        self::MODE_WA_ME       => 'Standard Click-to-Chat (wa.me link)',
        self::MODE_WEB_DIRECT  => 'WhatsApp Web (web.whatsapp.com)',
        self::MODE_DESKTOP_APP => 'WhatsApp Desktop App (whatsapp:// protocol)',
        self::MODE_GATEWAY     => 'WA Client / HTTP API Gateway (Automatic sending)',
    ];

    /** Setting keys in system_settings */
    public const KEY_CLIENT_MODE      = 'wa_client_mode';
    public const KEY_GATEWAY_URL      = 'wa_gateway_url';
    public const KEY_GATEWAY_KEY      = 'wa_gateway_key';
    public const KEY_GATEWAY_INSTANCE = 'wa_gateway_instance';
    public const KEY_METHOD           = 'wa_method';
    public const KEY_PAYLOAD_FORMAT   = 'wa_payload_format'; // 'json', 'form'
    public const KEY_PHONE_PARAM      = 'wa_phone_param';
    public const KEY_MESSAGE_PARAM    = 'wa_message_param';

    public function __construct()
    {
        $this->db = \Database::connect();
    }

    /**
     * Normalise a Sri Lankan mobile number to the 94XXXXXXXXX form that
     * wa.me links and the gateway both expect. Accepts 0771234567,
     * +94771234567, 94771234567, or 771234567, with or without spaces
     * or dashes. Returns null for anything that does not look like a
     * mobile number, so a bad row is skipped rather than producing a
     * link to nowhere.
     */
    public static function normalisePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        // 0771234567 -> 94771234567
        if (str_starts_with($digits, '0')) {
            $digits = self::COUNTRY_CODE . substr($digits, 1);
        } elseif (str_starts_with($digits, '7') && strlen($digits) === 9) {
            // 771234567, written without the leading zero
            $digits = self::COUNTRY_CODE . $digits;
        }

        // A Sri Lankan mobile is 94 then nine digits beginning with 7.
        if (!preg_match('/^947\d{8}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /**
     * Get all WA Client configuration settings.
     * @return array<string, string>
     */
    public function config(): array
    {
        $keys = [
            self::KEY_CLIENT_MODE,
            self::KEY_GATEWAY_URL,
            self::KEY_GATEWAY_KEY,
            self::KEY_GATEWAY_INSTANCE,
            self::KEY_METHOD,
            self::KEY_PAYLOAD_FORMAT,
            self::KEY_PHONE_PARAM,
            self::KEY_MESSAGE_PARAM,
        ];
        $defaults = [
            self::KEY_CLIENT_MODE      => self::MODE_WA_ME,
            self::KEY_GATEWAY_URL      => '',
            self::KEY_GATEWAY_KEY      => '',
            self::KEY_GATEWAY_INSTANCE => '',
            self::KEY_METHOD           => 'POST',
            self::KEY_PAYLOAD_FORMAT   => 'json',
            self::KEY_PHONE_PARAM      => 'number',
            self::KEY_MESSAGE_PARAM    => 'message',
        ];
        $settings = new \Setting();
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = (string) ($settings->get($k, $defaults[$k] ?? '') ?? $defaults[$k] ?? '');
        }
        return $out;
    }

    /**
     * Whether a WA Client REST gateway is configured.
     */
    public function isGatewayConfigured(): bool
    {
        $cfg = $this->config();
        return ($cfg[self::KEY_CLIENT_MODE] === self::MODE_GATEWAY)
            && filter_var($cfg[self::KEY_GATEWAY_URL], FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Dispatch a message via the configured WA Client REST API Gateway.
     * @return array{ok: bool, message: string, response?: mixed}
     */
    public function sendViaGateway(string $phone, string $body): array
    {
        $cfg = $this->config();
        $url = trim($cfg[self::KEY_GATEWAY_URL]);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'Invalid WA Client Gateway URL.'];
        }

        $normalised = self::normalisePhone($phone);
        if ($normalised === null) {
            return ['ok' => false, 'message' => 'Invalid phone number format.'];
        }

        $method = strtoupper($cfg[self::KEY_METHOD] ?: 'POST');
        $format = $cfg[self::KEY_PAYLOAD_FORMAT] ?: 'json';
        $phoneKey = $cfg[self::KEY_PHONE_PARAM] ?: 'number';
        $msgKey = $cfg[self::KEY_MESSAGE_PARAM] ?: 'message';
        $instance = $cfg[self::KEY_GATEWAY_INSTANCE] ?: '';
        $apiKey = $cfg[self::KEY_GATEWAY_KEY] ?: '';

        $params = [
            $phoneKey => $normalised,
            $msgKey   => $body,
        ];
        if ($instance !== '') {
            $params['instance_id'] = $instance;
            $params['session'] = $instance;
        }

        $ch = curl_init();
        $headers = ['User-Agent: Student-LMS-WA-Client/1.0'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        if ($method === 'GET') {
            $query = http_build_query($params);
            $fullUrl = str_contains($url, '?') ? ($url . '&' . $query) : ($url . '?' . $query);
            curl_setopt($ch, CURLOPT_URL, $fullUrl);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($format === 'json') {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false || $err) {
            return ['ok' => false, 'message' => 'Connection to WA Client failed: ' . $err];
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'response' => $response, 'message' => 'Message successfully dispatched to WA Client.'];
        }

        return ['ok' => false, 'status' => $status, 'message' => 'WA Client returned HTTP ' . $status . ': ' . mb_substr((string) $response, 0, 150)];
    }

    /**
     * Get the configured click-to-chat link.
     */
    public function getChatLink(string $phone, string $body): string
    {
        $cfg = $this->config();
        $mode = $cfg[self::KEY_CLIENT_MODE] ?? self::MODE_WA_ME;
        return match ($mode) {
            self::MODE_WEB_DIRECT  => 'https://web.whatsapp.com/send?phone=' . $phone . '&text=' . rawurlencode($body),
            self::MODE_DESKTOP_APP => 'whatsapp://send?phone=' . $phone . '&text=' . rawurlencode($body),
            default                => 'https://wa.me/' . $phone . '?text=' . rawurlencode($body),
        };
    }

    /**
     * The click-to-chat link for a composed message.
     */
    public static function chatLink(string $phone, string $body): string
    {
        try {
            return (new self())->getChatLink($phone, $body);
        } catch (\Throwable $e) {
            return 'https://wa.me/' . $phone . '?text=' . rawurlencode($body);
        }
    }

    /**
     * Fill a template's placeholders.
     *
     * An unknown placeholder is left exactly as written rather than
     * replaced with an empty string: a typo then shows up in the
     * preview as {studnet} instead of quietly deleting half a sentence
     * on its way to a parent.
     *
     * @param array<string, string|null> $values
     */
    public static function render(string $body, array $values): string
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        return $body;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(?string $category = null): array
    {
        $sql = "SELECT * FROM message_templates WHERE is_active = 1";
        $params = [];

        if ($category !== null) {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        $sql .= " ORDER BY category, name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function template(string $key): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM message_templates WHERE template_key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Everyone who owes money for a month, with the message already
     * written for each.
     *
     * One query rather than a loop of them: a branch with 200 students
     * is one round trip, and the teacher is waiting on this page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function unpaidRecipients(string $monthYear, ?int $classId = null, ?int $instituteId = null): array
    {
        $sql = "SELECT p.id AS payment_id, p.amount_due, p.amount_paid, p.payment_status,
                       p.month_year,
                       s.id AS student_id, s.full_name AS student_name,
                       s.student_id_display, s.phone1 AS student_phone,
                       s.whatsapp AS student_whatsapp,
                       par.parent_name, par.phone1 AS parent_phone, par.whatsapp AS parent_whatsapp,
                       c.id AS class_id, c.class_name, c.grade,
                       i.name AS institute_name,
                       (SELECT COUNT(*) FROM whatsapp_messages w
                         WHERE w.payment_id = p.id AND w.send_status = 'sent') AS reminders_sent
                  FROM payments p
                  JOIN students s ON s.id = p.student_id
                  JOIN classes  c ON c.id = p.class_id
                  LEFT JOIN institutes i ON i.id = c.institute_id
                  LEFT JOIN parents par ON par.student_id = s.id AND par.status = 'active'
                 WHERE p.month_year = :month
                   AND p.payment_status IN ('pending','overdue')
                   AND s.status = 'active'";

        $params = [':month' => $monthYear];

        if ($classId !== null) {
            $sql .= " AND c.id = :class_id";
            $params[':class_id'] = $classId;
        }

        if ($instituteId !== null) {
            $sql .= " AND c.institute_id = :institute_id";
            $params[':institute_id'] = $instituteId;
        }

        $sql .= " ORDER BY p.payment_status DESC, i.name, c.class_name, s.full_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pick the number to message, preferring the guardian's.
     *
     * A fee reminder is about the student but is for whoever pays, and
     * a child's own phone is the wrong place for it. Falls back to the
     * student's number when there is no guardian on record.
     *
     * @return array{name: string, phone: string, type: string}|null
     */
    public static function bestRecipient(array $row, bool $preferParent = true): ?array
    {
        $candidates = $preferParent
            ? [
                [$row['parent_name'] ?? null, $row['parent_whatsapp'] ?? null, 'parent'],
                [$row['parent_name'] ?? null, $row['parent_phone'] ?? null, 'parent'],
                [$row['student_name'] ?? null, $row['student_whatsapp'] ?? null, 'student'],
                [$row['student_name'] ?? null, $row['student_phone'] ?? null, 'student'],
            ]
            : [
                [$row['student_name'] ?? null, $row['student_whatsapp'] ?? null, 'student'],
                [$row['student_name'] ?? null, $row['student_phone'] ?? null, 'student'],
            ];

        foreach ($candidates as [$name, $phone, $type]) {
            $normalised = self::normalisePhone($phone);

            if ($normalised !== null) {
                return [
                    'name'  => $name ?: 'Guardian',
                    'phone' => $normalised,
                    'type'  => $type,
                ];
            }
        }

        return null;
    }

    /**
     * Write a message into the log as queued.
     */
    public function queue(array $message): int
    {
        $sql = "INSERT INTO whatsapp_messages
                       (student_id, class_id, payment_id, recipient_name, recipient_phone,
                        recipient_type, template_key, body, send_status)
                VALUES (:student_id, :class_id, :payment_id, :name, :phone,
                        :type, :template, :body, 'queued')";

        $this->db->prepare($sql)->execute([
            ':student_id' => $message['student_id'] ?? null,
            ':class_id'   => $message['class_id'] ?? null,
            ':payment_id' => $message['payment_id'] ?? null,
            ':name'       => $message['recipient_name'],
            ':phone'      => $message['recipient_phone'],
            ':type'       => $message['recipient_type'] ?? 'parent',
            ':template'   => $message['template_key'] ?? null,
            ':body'       => $message['body'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Mark a queued message as sent.
     *
     * Called when the teacher confirms after the chat link opened. It
     * records a human's word, which is the most this channel can offer,
     * and the enum comment in the schema says the same.
     */
    public function markSent(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE whatsapp_messages
                SET send_status = 'sent', sent_at = NOW(), sent_by = :user
              WHERE id = :id AND send_status = 'queued'"
        );
        $stmt->execute([':id' => $id, ':user' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function markSkipped(int $id, int $userId, ?string $note = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE whatsapp_messages
                SET send_status = 'skipped', sent_by = :user, note = :note
              WHERE id = :id AND send_status = 'queued'"
        );
        $stmt->execute([':id' => $id, ':user' => $userId, ':note' => $note]);

        return $stmt->rowCount() > 0;
    }

    /**
     * The send log, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function log(int $limit = 100, ?string $status = null): array
    {
        $sql = "SELECT w.*, s.full_name AS student_name, c.class_name,
                       u.full_name AS sent_by_name
                  FROM whatsapp_messages w
                  LEFT JOIN students s ON s.id = w.student_id
                  LEFT JOIN classes  c ON c.id = w.class_id
                  LEFT JOIN users    u ON u.id = w.sent_by";

        $params = [];

        if ($status !== null) {
            $sql .= " WHERE w.send_status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY w.created_at DESC, w.id DESC LIMIT " . max(1, min(500, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Counts for the summary strip.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $rows = $this->db->query(
            "SELECT send_status, COUNT(*) AS n FROM whatsapp_messages GROUP BY send_status"
        )->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['queued' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($rows as $row) {
            $counts[$row['send_status']] = (int) $row['n'];
        }

        return $counts;
    }
}
