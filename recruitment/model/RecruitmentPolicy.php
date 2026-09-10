<?php

declare(strict_types=1);

final class RecruitmentPolicy
{
    public const REQUISITION_TRANSITIONS = [
        'draft' => ['pending_approval', 'cancelled'],
        'pending_approval' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['cancelled'],
        'rejected' => ['draft', 'cancelled'],
        'cancelled' => [],
    ];

    public const JOB_TRANSITIONS = [
        'draft' => ['published', 'closed'],
        'published' => ['paused', 'closed', 'expired'],
        'paused' => ['published', 'closed', 'expired'],
        'closed' => [],
        'expired' => ['closed'],
    ];

    public const APPLICATION_TRANSITIONS = [
        'draft' => ['submitted', 'withdrawn'],
        'submitted' => ['reviewing', 'declined', 'withdrawn'],
        'reviewing' => ['shortlisted', 'interview', 'requirements', 'declined', 'withdrawn'],
        'shortlisted' => ['interview', 'requirements', 'declined', 'withdrawn'],
        'interview' => ['requirements', 'conditional_offer', 'declined', 'withdrawn'],
        'requirements' => ['interview', 'conditional_offer', 'declined', 'withdrawn'],
        'conditional_offer' => ['offer_accepted', 'offer_declined', 'declined', 'withdrawn'],
        'offer_accepted' => ['onboarding', 'withdrawn'],
        'offer_declined' => [],
        'onboarding' => ['ready_for_conversion', 'declined', 'withdrawn'],
        'ready_for_conversion' => ['converted', 'onboarding'],
        'converted' => [],
        'declined' => [],
        'withdrawn' => [],
    ];

    public const INTERVIEW_TRANSITIONS = [
        'draft' => ['scheduled', 'cancelled'],
        'scheduled' => ['confirmed', 'reschedule_requested', 'completed', 'cancelled'],
        'confirmed' => ['reschedule_requested', 'completed', 'cancelled'],
        'reschedule_requested' => ['scheduled', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const OFFER_TRANSITIONS = [
        'draft' => ['pending_approval', 'withdrawn'],
        'pending_approval' => ['approved', 'rejected', 'withdrawn'],
        'rejected' => ['draft', 'withdrawn'],
        'approved' => ['delivered', 'withdrawn'],
        'delivered' => ['accepted', 'declined', 'expired', 'withdrawn'],
        'accepted' => [],
        'declined' => [],
        'expired' => [],
        'withdrawn' => [],
    ];

    public const ONBOARDING_CASE_TRANSITIONS = [
        'not_started' => ['in_progress', 'cancelled'],
        'in_progress' => ['blocked', 'ready_for_review', 'cancelled'],
        'blocked' => ['in_progress', 'cancelled'],
        'ready_for_review' => ['in_progress', 'ready_for_conversion', 'cancelled'],
        'ready_for_conversion' => ['in_progress', 'converted', 'cancelled'],
        'converted' => [],
        'cancelled' => [],
    ];

    public const ONBOARDING_ITEM_TRANSITIONS = [
        'pending' => ['in_progress', 'submitted', 'waived', 'not_applicable'],
        'in_progress' => ['submitted', 'pending', 'waived', 'not_applicable'],
        'submitted' => ['approved', 'changes_requested'],
        'changes_requested' => ['in_progress', 'submitted'],
        'approved' => [],
        'waived' => [],
        'not_applicable' => [],
    ];

    public const CONVERSION_TRANSITIONS = [
        'draft' => ['pending_duplicate_check', 'cancelled'],
        'pending_duplicate_check' => ['pending_approval', 'blocked', 'cancelled'],
        'blocked' => ['pending_duplicate_check', 'cancelled'],
        'pending_approval' => ['approved', 'rejected', 'cancelled'],
        'rejected' => ['draft', 'cancelled'],
        'approved' => ['executed', 'blocked'],
        'executed' => ['reconciled', 'reconciliation_failed'],
        'reconciliation_failed' => ['reconciled'],
        'reconciled' => [],
        'cancelled' => [],
    ];

    public const CANDIDATE_VISIBLE_STATUS = [
        'draft' => 'Draft',
        'submitted' => 'Application received',
        'reviewing' => 'Under review',
        'shortlisted' => 'Moving forward',
        'interview' => 'Interview stage',
        'requirements' => 'Action required',
        'conditional_offer' => 'Offer review',
        'offer_accepted' => 'Offer accepted',
        'offer_declined' => 'Offer declined',
        'onboarding' => 'Onboarding in progress',
        'ready_for_conversion' => 'Onboarding complete',
        'converted' => 'Employee record created',
        'declined' => 'Application closed',
        'withdrawn' => 'Application withdrawn',
    ];

    public static function canTransition(string $workflow, string $from, string $to): bool
    {
        $map = match ($workflow) {
            'requisition' => self::REQUISITION_TRANSITIONS,
            'job' => self::JOB_TRANSITIONS,
            'application' => self::APPLICATION_TRANSITIONS,
            'interview' => self::INTERVIEW_TRANSITIONS,
            'offer' => self::OFFER_TRANSITIONS,
            'onboarding_case' => self::ONBOARDING_CASE_TRANSITIONS,
            'onboarding_item' => self::ONBOARDING_ITEM_TRANSITIONS,
            'conversion' => self::CONVERSION_TRANSITIONS,
            default => [],
        };

        return in_array($to, $map[$from] ?? [], true);
    }

    public static function candidateStatus(string $internalStatus): string
    {
        return self::CANDIDATE_VISIBLE_STATUS[$internalStatus] ?? 'Status unavailable';
    }

    public static function publicJobCanAcceptApplications(array $job, ?DateTimeImmutable $now = null): bool
    {
        if (($job['publication_status'] ?? '') !== 'published') {
            return false;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $opensAt = self::parseDate($job['opens_at'] ?? null);
        $closesAt = self::parseDate($job['closes_at'] ?? null);

        return ($opensAt === null || $opensAt <= $now)
            && ($closesAt === null || $closesAt > $now);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public static function emailLookupHash(string $email, string $lookupKey): string
    {
        if (strlen($lookupKey) < 32) {
            throw new InvalidArgumentException('The recruitment lookup key must contain at least 32 characters.');
        }

        $normalized = self::normalizeEmail($email);
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        return hash_hmac('sha256', $normalized, $lookupKey);
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable((string)$value, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }
}
