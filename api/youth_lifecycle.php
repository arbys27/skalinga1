<?php

function youthAgeFromBirthday(string $birthday): ?int
{
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthday);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$birthDate
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $birthDate->format('Y-m-d') !== $birthday
        || $birthDate > new DateTimeImmutable('today')) {
        return null;
    }

    return $birthDate->diff(new DateTimeImmutable('today'))->y;
}

function setYouthLifecycleStatus(
    PDO $pdo,
    string $recordType,
    int $recordId,
    string $newStatus,
    string $reason,
    ?int $adminId,
    string $adminUsername
): bool {
    if ($recordType === 'user') {
        $table = 'users';
        $foreignKey = 'user_id';
    } elseif ($recordType === 'masterlist') {
        $table = 'kk_masterlist';
        $foreignKey = 'masterlist_id';
    } else {
        throw new InvalidArgumentException('Unsupported lifecycle record type.');
    }

    $stmt = $pdo->prepare("SELECT status FROM public.{$table} WHERE id = ? FOR UPDATE");
    $stmt->execute([$recordId]);
    $previousStatus = $stmt->fetchColumn();
    if ($previousStatus === false || $previousStatus === $newStatus) {
        return false;
    }

    $stmt = $pdo->prepare(
        "UPDATE public.{$table}
         SET status = ?, status_reason = ?, status_changed_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );
    $stmt->execute([$newStatus, $reason, $recordId]);

    $stmt = $pdo->prepare(
        "INSERT INTO public.youth_status_history
         ({$foreignKey}, previous_status, new_status, reason, changed_by_admin_id, changed_by_username)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$recordId, $previousStatus, $newStatus, $reason, $adminId, $adminUsername]);
    return true;
}

function runYouthLifecycle(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $users = $pdo->query(
            "SELECT u.id, u.status
             FROM public.users u
             JOIN public.youth_profiles p ON p.user_id = u.id
             WHERE u.status <> 'archived'
               AND p.birthday IS NOT NULL
               AND p.birthday <= CURRENT_DATE - INTERVAL '31 years'
             FOR UPDATE OF u"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            setYouthLifecycleStatus(
                $pdo,
                'user',
                (int)$user['id'],
                'archived',
                'Age exceeded 30 years.',
                null,
                'system'
            );
        }

        $masterlistAgeExceeded = $pdo->query(
            "SELECT id
             FROM public.kk_masterlist
             WHERE status <> 'archived'
               AND birthday <= CURRENT_DATE - INTERVAL '31 years'
             FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($masterlistAgeExceeded as $masterlistId) {
            setYouthLifecycleStatus(
                $pdo,
                'masterlist',
                (int)$masterlistId,
                'archived',
                'Age exceeded 30 years.',
                null,
                'system'
            );
        }

        $inactiveUsers = $pdo->query(
            "SELECT id
             FROM public.users
             WHERE status = 'active'
               AND COALESCE(last_login, created_at) <= CURRENT_TIMESTAMP - INTERVAL '5 years'
             FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($inactiveUsers as $userId) {
            setYouthLifecycleStatus(
                $pdo,
                'user',
                (int)$userId,
                'inactive',
                'No account activity for five years.',
                null,
                'system'
            );
        }

        $unactivatedRecords = $pdo->query(
            "SELECT id
             FROM public.kk_masterlist
             WHERE status = 'active'
               AND is_claimed = FALSE
               AND created_at <= CURRENT_TIMESTAMP - INTERVAL '5 years'
             FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($unactivatedRecords as $masterlistId) {
            setYouthLifecycleStatus(
                $pdo,
                'masterlist',
                (int)$masterlistId,
                'inactive',
                'KK Masterlist account was not activated within five years.',
                null,
                'system'
            );
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
