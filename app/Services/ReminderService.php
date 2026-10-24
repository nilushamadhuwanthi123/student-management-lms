<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Turns calendar occurrences into notifications, exactly once each.
 *
 * Two reminders go out for every occurrence: one the day before, and
 * one an hour before. The teacher asked for both, and they do different
 * jobs -- the day-before one is so a parent can plan, the hour-before
 * one is so nobody forgets on the day.
 *
 * ## Why running this twice is safe
 *
 * This is driven by a job. A job gets run by a scheduler every ten
 * minutes, run again by hand when someone is impatient, and run a third
 * time after a crash halfway through. None of that may produce a second
 * notification for the same thing.
 *
 * The guard is a unique key on `reminder_dispatches`, not a check in
 * PHP. "SELECT, then INSERT if absent" is the obvious approach and it is
 * wrong: two runs overlapping by a second both pass the SELECT before
 * either INSERTs. Here the database arbitrates -- a second attempt hits
 * the unique key, the insert is refused, and that recipient is skipped.
 * The code below treats a duplicate-key error as a normal outcome
 * rather than a failure, because that is exactly what it is.
 *
 * ## Why it catches up instead of firing in a narrow window
 *
 * Each lead reminds about anything happening between now and now + lead,
 * not about things landing precisely one hour out. So if the scheduler
 * was off all night, the next run still sends the reminders it owes,
 * once, instead of silently skipping a window it missed. Nothing in the
 * past is reminded about: a reminder for a class that already finished
 * is noise.
 *
 * The fee gate is deliberately not consulted. A student behind on the
 * month's fee still gets told their class is on tomorrow -- knowing when
 * to turn up is not the paid content, and withholding it would punish
 * the family twice.
 */
class ReminderService
{
    /** A day before, then an hour before. */
    public const LEADS = [1440, 60];

    private PDO $db;
    private CalendarService $calendar;

    /** The clock for the current run, so wording agrees with the band that chose it. */
    private ?DateTimeImmutable $now = null;

    public function __construct()
    {
        $this->db = \Database::connect();
        $this->calendar = new CalendarService();
    }

    /**
     * Send every reminder that is now due and has not been sent.
     *
     * @return array<string, int> counts, for the runner to report
     */
    public function dispatchDue(?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable($this->databaseNow());
        $this->now = $now;

        $counts = ['occurrences' => 0, 'sent' => 0, 'already_sent' => 0, 'no_recipients' => 0];

        // Each lead owns a band of time, not everything up to its horizon.
        //
        // The first version let every lead reach from now to its horizon,
        // which meant that at 09:30 the day-before lead also caught a
        // 10:00 exam and sent "Tomorrow: Exam" about something half an
        // hour away, alongside the hour-before reminder. So the day-before
        // lead now covers only (1 hour, 1 day] ahead, and the hour-before
        // lead covers (now, 1 hour]. Catch-up still works inside a band --
        // a job that was off overnight still owes the day-before reminder
        // at breakfast -- but a stale reminder is never sent once a closer
        // one applies.
        $leads = self::LEADS;
        rsort($leads);

        foreach ($leads as $index => $lead) {
            $horizon = $now->modify("+{$lead} minutes");
            $closer = $leads[$index + 1] ?? null;
            $floor = $closer !== null ? $now->modify("+{$closer} minutes") : $now;

            // The expansion is asked for whole days because a weekly rule
            // is only meaningful per date; the time comparison below does
            // the precise work.
            $occurrences = $this->calendar->between(
                new DateTimeImmutable($now->format('Y-m-d')),
                new DateTimeImmutable($horizon->format('Y-m-d'))
            );

            foreach ($occurrences as $occurrence) {
                if (!$this->remindable($occurrence)) {
                    continue;
                }

                $startsAt = $occurrence['starts_at'];

                // Inside this lead's band: after its floor (now, for the
                // closest lead -- so nothing that has begun is announced)
                // and at or before its horizon.
                if ($startsAt <= $floor->format('Y-m-d H:i:s') || $startsAt > $horizon->format('Y-m-d H:i:s')) {
                    continue;
                }

                $counts['occurrences']++;
                $recipients = $this->recipientsFor($occurrence);

                if ($recipients === []) {
                    $counts['no_recipients']++;
                    continue;
                }

                $result = $this->send($occurrence, $lead, $recipients);
                $counts['sent'] += $result['sent'];
                $counts['already_sent'] += $result['skipped'];
            }
        }

        return $counts;
    }

    /**
     * Should this kind of occurrence be reminded about at all?
     *
     * A quiz opening is informational and would double up with the
     * deadline reminder on a one-day quiz, so only the deadline is
     * reminded about. Events honour their own `remind` flag: a notice
     * pinned for the month does not need to wake anyone.
     *
     * @param array<string, mixed> $occurrence
     */
    private function remindable(array $occurrence): bool
    {
        if ($occurrence['type'] === 'quiz_opens') {
            return false;
        }

        if ($occurrence['type'] === 'event') {
            return (bool) ($occurrence['remind'] ?? true);
        }

        return true;
    }

    /**
     * Who hears about this occurrence.
     *
     * A class-bound occurrence reaches that class's active students,
     * their guardians and the teacher. A school-wide event (no class)
     * reaches every active student and guardian, plus the teacher.
     *
     * @param array<string, mixed> $occurrence
     * @return array<int, array{type: string, id: int}>
     */
    private function recipientsFor(array $occurrence): array
    {
        $classId = $occurrence['class_id'] ?? null;
        $recipients = [];

        if ($classId !== null) {
            $stmt = $this->db->prepare(
                "SELECT s.id
                   FROM enrollments e
                   JOIN students s ON s.id = e.student_id AND s.status = 'active'
                  WHERE e.class_id = :class_id
                    AND e.enrollment_status = 'active'"
            );
            $stmt->execute([':class_id' => (int) $classId]);
            $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $stmt = $this->db->prepare(
                "SELECT teacher_id FROM classes WHERE id = :class_id"
            );
            $stmt->execute([':class_id' => (int) $classId]);
            $teacherId = $stmt->fetchColumn();

            if ($teacherId !== false && $teacherId !== null) {
                $recipients[] = ['type' => 'user', 'id' => (int) $teacherId];
            }
        } else {
            $studentIds = array_map(
                'intval',
                $this->db->query("SELECT id FROM students WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN)
            );

            $teacherId = $this->db->query(
                "SELECT id FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY id LIMIT 1"
            )->fetchColumn();

            if ($teacherId !== false) {
                $recipients[] = ['type' => 'user', 'id' => (int) $teacherId];
            }
        }

        foreach ($studentIds as $studentId) {
            $recipients[] = ['type' => 'student', 'id' => $studentId];
        }

        if ($studentIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT id FROM parents WHERE student_id IN ({$placeholders}) AND status = 'active'"
            );
            $stmt->execute($studentIds);

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $parentId) {
                $recipients[] = ['type' => 'parent', 'id' => (int) $parentId];
            }
        }

        return $recipients;
    }

    /**
     * @param array<string, mixed> $occurrence
     * @param array<int, array{type: string, id: int}> $recipients
     * @return array{sent: int, skipped: int}
     */
    private function send(array $occurrence, int $lead, array $recipients): array
    {
        return $this->notifyOnce(
            $this->sourceType($occurrence),
            (int) $occurrence['source_id'],
            (string) $occurrence['starts_at'],
            $lead,
            $recipients,
            $this->titleFor($occurrence, $lead),
            $this->bodyFor($occurrence)
        );
    }

    /**
     * Notify a list of people about one thing, at most once each, ever.
     *
     * This is the only place that writes a notification, and the unique
     * key on `reminder_dispatches` is what makes "at most once" true
     * even when two runs overlap. Content announcements use it as well
     * as reminders: re-saving a material must not tell thirty families
     * about it a second time.
     *
     * `occurrenceAt` is what distinguishes one instance of a recurring
     * thing from the next, so for a one-off announcement it is simply
     * when the thing was created.
     *
     * @param array<int, array{type: string, id: int}> $recipients
     * @return array{sent: int, skipped: int}
     */
    public function notifyOnce(
        string $sourceType,
        int $sourceId,
        string $occurrenceAt,
        int $lead,
        array $recipients,
        string $title,
        string $body
    ): array {
        $sent = 0;
        $skipped = 0;

        $claim = $this->db->prepare(
            "INSERT INTO reminder_dispatches
                 (source_type, source_id, occurrence_at, lead_minutes, recipient_type, recipient_id)
             VALUES (:source_type, :source_id, :occurrence_at, :lead_minutes, :recipient_type, :recipient_id)"
        );

        $notify = $this->db->prepare(
            "INSERT INTO notifications
                 (recipient_id, recipient_type, title, body, notification_type,
                  related_entity_type, related_entity_id)
             VALUES (:recipient_id, :recipient_type, :title, :body, 'info',
                     :entity_type, :entity_id)"
        );

        $link = $this->db->prepare(
            "UPDATE reminder_dispatches SET notification_id = :notification_id WHERE id = :id"
        );

        foreach ($recipients as $recipient) {
            try {
                $claim->execute([
                    ':source_type'    => $sourceType,
                    ':source_id'      => $sourceId,
                    ':occurrence_at'  => $occurrenceAt,
                    ':lead_minutes'   => $lead,
                    ':recipient_type' => $recipient['type'],
                    ':recipient_id'   => $recipient['id'],
                ]);
            } catch (PDOException $e) {
                // 23000 is the unique key doing its job: this reminder
                // has already gone out. Not an error.
                if ($e->getCode() === '23000') {
                    $skipped++;
                    continue;
                }

                throw $e;
            }

            $dispatchId = (int) $this->db->lastInsertId();

            $notify->execute([
                ':recipient_id'   => $recipient['id'],
                ':recipient_type' => $recipient['type'],
                ':title'          => $title,
                ':body'           => $body,
                ':entity_type'    => $sourceType,
                ':entity_id'      => $sourceId,
            ]);

            $link->execute([
                ':notification_id' => (int) $this->db->lastInsertId(),
                ':id'              => $dispatchId,
            ]);

            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $occurrence
     */
    private function sourceType(array $occurrence): string
    {
        return match ($occurrence['type']) {
            'class_session' => 'class_session',
            'quiz_closes'   => 'quiz_closes',
            'quiz_opens'    => 'quiz_opens',
            default         => 'event',
        };
    }

    /**
     * A title that says when, because a notification list is read
     * without its timestamp in mind.
     *
     * @param array<string, mixed> $occurrence
     */
    private function titleFor(array $occurrence, int $lead): string
    {
        // Worded from the real date, not from which lead fired. The
        // day-before band reaches from one hour to one day ahead, so it
        // can hold something at six this evening; calling that
        // "tomorrow" would send a family to the wrong day.
        $today = $this->now !== null ? $this->now->format('Y-m-d') : date('Y-m-d');
        $time = $occurrence['start_time'] !== null ? ' at ' . $occurrence['start_time'] : '';

        $when = match (true) {
            $lead < 1440                   => 'In about an hour',
            $occurrence['date'] === $today => 'Today' . $time,
            default                        => 'Tomorrow' . $time,
        };

        if ($occurrence['type'] === 'quiz_closes') {
            return "Quiz due - {$when}: {$occurrence['title']}";
        }

        return "{$when}: {$occurrence['detail']} - {$occurrence['title']}";
    }

    /**
     * @param array<string, mixed> $occurrence
     */
    private function bodyFor(array $occurrence): string
    {
        $parts = [];

        $date = date('l j F', strtotime($occurrence['date']));
        $parts[] = $occurrence['start_time'] !== null
            ? "{$date} at {$occurrence['start_time']}"
            : $date;

        if (!empty($occurrence['class_name'])) {
            $parts[] = (string) $occurrence['class_name'];
        }

        if (!empty($occurrence['room'])) {
            $parts[] = "Room {$occurrence['room']}";
        }

        if (!empty($occurrence['syllabus'])) {
            $parts[] = "Covers: {$occurrence['syllabus']}";
        } elseif (!empty($occurrence['description'])) {
            $parts[] = (string) $occurrence['description'];
        }

        return implode(' - ', $parts) . '.';
    }

    /**
     * The clock the reminders are measured against.
     *
     * The database clock, not PHP's, for the same reason the quiz timer
     * uses it: when the two disagree, one of them has to be the
     * authority, and every stored occurrence_at came from the database.
     */
    private function databaseNow(): string
    {
        return (string) $this->db->query('SELECT NOW()')->fetchColumn();
    }
}
