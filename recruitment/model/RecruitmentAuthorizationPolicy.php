<?php

declare(strict_types=1);

final class RecruitmentAuthorizationPolicy
{
    public const CAPABILITIES = [
        'requisition.view', 'requisition.prepare', 'requisition.approve',
        'job.view', 'job.prepare', 'job.publish',
        'application.view', 'application.manage', 'candidate.view', 'candidate.assign',
        'interview.manage', 'offer.prepare', 'offer.approve',
        'onboarding.manage', 'document.request', 'document.review',
        'employee_conversion.prepare', 'employee_conversion.approve',
        'access.manage', 'audit.view',
    ];

    public static function capabilityIsKnown(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    public static function grantIsEffective(array $grant, string $username, string $capability, string $scopeType, string $scopeReference, ?DateTimeImmutable $now = null): bool
    {
        if (!self::capabilityIsKnown($capability) || !hash_equals((string)($grant['username'] ?? ''), $username)) {
            return false;
        }
        if (($grant['capability'] ?? '') !== $capability || ($grant['revoked_at'] ?? null) !== null) {
            return false;
        }
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (!empty($grant['expires_at'])) {
            try {
                if (new DateTimeImmutable((string)$grant['expires_at'], new DateTimeZone('UTC')) <= $now) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }
        return (($grant['scope_type'] ?? '') === 'global' && ($grant['scope_reference'] ?? '') === '*')
            || (($grant['scope_type'] ?? '') === $scopeType && ($grant['scope_reference'] ?? '') === $scopeReference);
    }

    public static function makerCheckerSatisfied(string $preparedBy, string $approvedBy): bool
    {
        return $preparedBy !== '' && $approvedBy !== '' && !hash_equals($preparedBy, $approvedBy);
    }
}
