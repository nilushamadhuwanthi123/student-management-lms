<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Tell a family when a child is marked absent from a class.
 *
 * Once per student, class and day (absence_alerts has a unique key), so
 * saving the register again does not message anyone twice. Only today's and
 * yesterday's registers send alerts: back-filling last month's register must
 * not wake a parent up about it now.
 *
 * Each alert is an in-portal notification to the parent(s) and the student,
 * plus a WhatsApp message prepared (queued) for staff to send. Nothing is
 * sent to WhatsApp automatically; see docs/WHATSAPP.md.
 */
final class AbsenceAlertService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    public static function dateIsRecent(string $date): bool
    {
        return $date >= date('Y-m-d', strtotime('-1 day')) && $date <= date('Y-m-d');
    }

    /** @return array{created: bool, parents: int, whatsapp: ?int} */
    public function alert(int $studentId, int $classId, string $date, ?int $byUser): array
    {
        $none = ['created' => false, 'parents' => 0, 'whatsapp' => null];
        if (!self::dateIsRecent($date)) {
            return $none;
        }

        $ins = $this->db->prepare("INSERT IGNORE INTO absence_alerts (student_id, class_id, date, created_by) VALUES (:s, :c, :d, :by)");
        $ins->execute([':s' => $studentId, ':c' => $classId, ':d' => $date, ':by' => $byUser]);
        if ($ins->rowCount() === 0) {
            return $none;
        }
        $alertId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "SELECT s.full_name AS student_name, s.phone1 AS student_phone, s.whatsapp AS student_whatsapp,
                    c.class_name, u.full_name AS teacher_name
               FROM students s JOIN classes c ON c.id = :c LEFT JOIN users u ON u.id = c.teacher_id
              WHERE s.id = :s"
        );
        $stmt->execute([':s' => $studentId, ':c' => $classId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($info === false) {
            return ['created' => true, 'parents' => 0, 'whatsapp' => null];
        }

        $when = $date === date('Y-m-d') ? 'today' : 'yesterday';
        $title = $info['student_name'] . ' was marked absent from ' . $info['class_name'] . ' ' . $when . '.';
        $body = 'The lesson notes and recording for this month are on the portal. If this is a mistake, please contact the branch office.';
        $notify = $this->db->prepare(
            "INSERT INTO notifications (recipient_id, recipient_type, title, body, notification_type, related_entity_type, related_entity_id)
             VALUES (:r, :t, :title, :body, 'warning', 'absence', :e)"
        );

        $parents = $this->db->prepare("SELECT id, parent_name, phone1, whatsapp FROM parents WHERE student_id = :s AND status = 'active' ORDER BY id");
        $parents->execute([':s' => $studentId]);
        $parentRows = $parents->fetchAll(PDO::FETCH_ASSOC);
        foreach ($parentRows as $p) {
            $notify->execute([':r' => (int) $p['id'], ':t' => 'parent', ':title' => $title, ':body' => $body, ':e' => $alertId]);
        }
        $notify->execute([':r' => $studentId, ':t' => 'student', ':title' => $title, ':body' => $body, ':e' => $alertId]);

        // A WhatsApp message prepared for staff to send (queued, not sent).
        $whatsappId = null;
        try {
            $wa = new WhatsAppService();
            $template = $wa->template('absence_alert');
            $first = $parentRows[0] ?? [];
            $to = WhatsAppService::bestRecipient([
                'parent_name' => $first['parent_name'] ?? null, 'parent_whatsapp' => $first['whatsapp'] ?? null, 'parent_phone' => $first['phone1'] ?? null,
                'student_name' => $info['student_name'], 'student_whatsapp' => $info['student_whatsapp'], 'student_phone' => $info['student_phone'],
            ]);
            if ($template !== null && $to !== null) {
                $whatsappId = $wa->queue([
                    'student_id' => $studentId, 'class_id' => $classId,
                    'recipient_name' => $to['name'], 'recipient_phone' => $to['phone'], 'recipient_type' => $to['type'],
                    'template_key' => 'absence_alert',
                    'body' => WhatsAppService::render((string) $template['body'], [
                        'student' => $info['student_name'], 'class' => $info['class_name'], 'teacher' => $info['teacher_name'] ?? '',
                    ]),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('Absence WhatsApp message not prepared: ' . $e->getMessage());
        }

        $this->db->prepare("UPDATE absence_alerts SET parents_notified = :n, whatsapp_id = :w WHERE id = :id")
            ->execute([':n' => count($parentRows), ':w' => $whatsappId, ':id' => $alertId]);

        return ['created' => true, 'parents' => count($parentRows), 'whatsapp' => $whatsappId];
    }
}
