<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * "@Full Name" mentions inside free text (a help-desk reply, a notice
 * body) that turn into a personal notification to the mentioned staff
 * member, through the exact same `notifications` table and delivery
 * path (AnnouncementService::notifyAll()) every other module already
 * uses -- this never invents a second notification channel.
 *
 * A mention never widens who can see the underlying thread or notice;
 * it only adds an extra personal nudge, and only to someone within the
 * mentioning user's own branches (see visibleTo() below) -- so this
 * cannot be used to probe for or notify a staff member in a branch the
 * author has no business with.
 */
class MentionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Database::connect();
    }

    /**
     * Active staff/teacher/coordinator/admin/super_admin ids whose exact
     * full name appears after an "@" in $text, excluding the author and
     * anyone outside the author's branches.
     *
     * Matched against each staff member's real full_name rather than by
     * parsing an arbitrary word-run after the "@" -- a generic "capture
     * up to N words" pattern swallows ordinary following words ("@Jane
     * Silva and the others" would otherwise capture "Jane Silva and").
     * Longest names are checked first only so a shorter name that is a
     * prefix of a longer one ("Jane" vs "Jane Silva") cannot shadow it,
     * though in practice each match is independent per staff member.
     *
     * @return int[]
     */
    public function mentionedStaffIds(string $text, int $authorUserId, ?BranchScope $scope = null): array
    {
        if (!str_contains($text, '@')) {
            return [];
        }

        $staff = $this->db->query(
            "SELECT id, full_name FROM users
              WHERE status = 'active' AND role IN ('super_admin','admin','coordinator','staff','teacher')"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($staff === []) {
            return [];
        }
        usort($staff, static fn (array $a, array $b): int => strlen((string) $b['full_name']) <=> strlen((string) $a['full_name']));

        // No scope passed (e.g. a cron run with no logged-in session, like
        // NoticeService::announceDue() for a scheduled notice): build one
        // from the author's own stored role rather than falling back to
        // BranchScope::current(), which would see no session at all.
        if ($scope === null) {
            $roleStmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
            $roleStmt->execute([':id' => $authorUserId]);
            $authorRole = (string) ($roleStmt->fetchColumn() ?: 'none');
            $scope = new BranchScope($authorUserId, $authorRole, $this->db);
        }

        $ids = [];
        foreach ($staff as $u) {
            $uid = (int) $u['id'];
            if ($uid === $authorUserId) {
                continue;
            }
            $full = (string) $u['full_name'];
            if ($full === '') {
                continue;
            }
            // "@Full Name" followed by anything that is not itself part
            // of a name (so "@Jane Silvakumar" never matches "Jane Silva").
            $pattern = '/@' . preg_quote($full, '/') . "(?![A-Za-z'])/ui";
            if (preg_match($pattern, $text) === 1 && $this->visibleTo($scope, $uid)) {
                $ids[] = $uid;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether $scope's branches overlap the target staff member's
     * branches -- deliberately looser than BranchScope::allowsUser(),
     * which also refuses a teacher outright (that method is for
     * managing a user's account, not just reaching them with a nudge).
     * A mention is informational only, so a teacher may mention a
     * coordinator/admin/fellow teacher at one of her own branches.
     */
    private function visibleTo(BranchScope $scope, int $targetUserId): bool
    {
        if ($scope->isGlobal()) {
            return true;
        }
        $mine = $scope->instituteIds() ?? [];
        if ($mine === []) {
            return false;
        }
        $in = implode(',', array_map('intval', $mine));
        $stmt = $this->db->prepare(
            "SELECT 1 FROM users u WHERE u.id = :t
               AND (EXISTS (SELECT 1 FROM user_institutes ui WHERE ui.user_id = u.id AND ui.institute_id IN ($in))
                 OR EXISTS (SELECT 1 FROM classes c WHERE c.teacher_id = u.id AND c.institute_id IN ($in)))"
        );
        $stmt->execute([':t' => $targetUserId]);

        return (bool) $stmt->fetchColumn();
    }
}
