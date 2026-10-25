<?php

declare(strict_types=1);

/**
 * Picks approvers for a deactivation or deletion request from the
 * initiator's own branch first.
 *
 * The workflow code takes "the first active coordinator" and "the first
 * active admin". With three branches that sent a Kandy request to the
 * Pannipitiya coordinator. This wrapper keeps the same getByRole() call
 * the workflow code already makes, but puts same-branch people at the
 * front of the list. If the branch has nobody in that role, everyone
 * else follows, so no request is left without an approver.
 */
class BranchAwareApprovers
{
    private User $users;
    private int $initiatorId;

    public function __construct(User $users, int $initiatorId)
    {
        $this->users = $users;
        $this->initiatorId = $initiatorId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getByRole(string $role, ?string $status = null): array
    {
        $all = $status === null ? $this->users->getByRole($role) : $this->users->getByRole($role, $status);
        if ($all === [] || $role === 'super_admin') {
            return $all;
        }

        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT institute_id FROM user_institutes WHERE user_id = :u1
             UNION SELECT id FROM institutes WHERE coordinator_id = :u2
             UNION SELECT c.institute_id FROM classes c WHERE c.teacher_id = :u3"
        );
        $stmt->execute([':u1' => $this->initiatorId, ':u2' => $this->initiatorId, ':u3' => $this->initiatorId]);
        $mine = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($mine === []) {
            return $all;
        }

        $in = implode(',', $mine);
        $same = array_map('intval', $db->query(
            "SELECT user_id FROM user_institutes WHERE institute_id IN ($in)
             UNION SELECT coordinator_id FROM institutes WHERE id IN ($in) AND coordinator_id IS NOT NULL"
        )->fetchAll(PDO::FETCH_COLUMN));

        usort($all, static function (array $a, array $b) use ($same): int {
            // Same branch sorts first; PHP 8's sort is stable, so the
            // original order is kept within each group.
            return (in_array((int) $a['id'], $same, true) ? 0 : 1) <=> (in_array((int) $b['id'], $same, true) ? 0 : 1);
        });

        return $all;
    }
}
