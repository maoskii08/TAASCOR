<?php

declare(strict_types=1);

require_once __DIR__ . '/RecruitmentAuthorizationPolicy.php';

final class RecruitmentStaffAccessService
{
    public function __construct(private PDO $db)
    {
    }

    public function can(string $username, string $capability, string $scopeType = 'global', string $scopeReference = '*'): bool
    {
        $username = trim($username);
        $scopeType = trim($scopeType);
        $scopeReference = trim($scopeReference);
        if ($username === '' || !RecruitmentAuthorizationPolicy::capabilityIsKnown($capability)) {
            return false;
        }

        $statement = $this->db->prepare(
            "SELECT username, capability, scope_type, scope_reference, expires_at, revoked_at
               FROM recruitment_staff_capability_grants
              WHERE username = :username
                AND capability = :capability
                AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                AND (
                    (scope_type = 'global' AND scope_reference = '*')
                    OR (scope_type = :scope_type AND scope_reference = :scope_reference)
                )
              ORDER BY CASE WHEN scope_type = 'global' THEN 1 ELSE 0 END
              LIMIT 1"
        );
        $statement->execute([
            'username' => $username,
            'capability' => $capability,
            'scope_type' => $scopeType,
            'scope_reference' => $scopeReference,
        ]);
        $grant = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($grant)
            && RecruitmentAuthorizationPolicy::grantIsEffective($grant, $username, $capability, $scopeType, $scopeReference);
    }

    public function assertCapability(string $username, string $capability, string $scopeType = 'global', string $scopeReference = '*'): void
    {
        if (!$this->can($username, $capability, $scopeType, $scopeReference)) {
            throw new DomainException('This recruitment action is not assigned to the current staff account.');
        }
    }

    /** @return list<string> */
    public function effectiveCapabilities(string $username, string $scopeType = 'global', string $scopeReference = '*'): array
    {
        $statement = $this->db->prepare(
            "SELECT username, capability, scope_type, scope_reference, expires_at, revoked_at
               FROM recruitment_staff_capability_grants
              WHERE username = :username
                AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                AND (
                    (scope_type = 'global' AND scope_reference = '*')
                    OR (scope_type = :scope_type AND scope_reference = :scope_reference)
                )"
        );
        $statement->execute([
            'username' => trim($username),
            'scope_type' => trim($scopeType),
            'scope_reference' => trim($scopeReference),
        ]);

        $capabilities = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $grant) {
            $capability = (string)($grant['capability'] ?? '');
            if (RecruitmentAuthorizationPolicy::grantIsEffective($grant, trim($username), $capability, $scopeType, $scopeReference)) {
                $capabilities[] = $capability;
            }
        }

        return array_values(array_unique($capabilities));
    }

    public function grant(string $username,string $capability,string $scopeType,string $scopeReference,?string $expiresAt,string $reason,string $actor): void
    {
        $this->assertCapability($actor,'access.manage');
        $username=trim($username); $scopeType=trim($scopeType); $scopeReference=trim($scopeReference); $reason=trim($reason);
        if ($username==='' || !RecruitmentAuthorizationPolicy::capabilityIsKnown($capability) || !in_array($scopeType,['global','requisition','job','application','client'],true) || $scopeReference==='' || $reason==='') throw new InvalidArgumentException('A valid account, capability, scope, and reason are required.');
        if ($scopeType==='global' && $scopeReference!=='*') throw new InvalidArgumentException('Global grants must use the global scope reference.');
        $statement=$this->db->prepare("INSERT INTO recruitment_staff_capability_grants (username,capability,scope_type,scope_reference,granted_by_username,reason,expires_at,revoked_at,revoked_by_username) VALUES (:username,:capability,:scope_type,:scope_reference,:actor,:reason,:expires_at,NULL,NULL) ON DUPLICATE KEY UPDATE granted_by_username=VALUES(granted_by_username),reason=VALUES(reason),expires_at=VALUES(expires_at),revoked_at=NULL,revoked_by_username=NULL,granted_at=UTC_TIMESTAMP()");
        $statement->execute(['username'=>$username,'capability'=>$capability,'scope_type'=>$scopeType,'scope_reference'=>$scopeReference,'actor'=>$actor,'reason'=>$reason,'expires_at'=>$expiresAt]);
    }

    public function revoke(string $username,string $capability,string $scopeType,string $scopeReference,string $reason,string $actor): void
    {
        $this->assertCapability($actor,'access.manage');
        if (trim($reason)==='') throw new InvalidArgumentException('A revocation reason is required.');
        $statement=$this->db->prepare("UPDATE recruitment_staff_capability_grants SET revoked_at=UTC_TIMESTAMP(),revoked_by_username=:actor,reason=CONCAT(reason, ' | Revoked: ', :reason) WHERE username=:username AND capability=:capability AND scope_type=:scope_type AND scope_reference=:scope_reference AND revoked_at IS NULL");
        $statement->execute(['actor'=>$actor,'reason'=>trim($reason),'username'=>trim($username),'capability'=>$capability,'scope_type'=>$scopeType,'scope_reference'=>$scopeReference]);
        if ($statement->rowCount()!==1) throw new DomainException('The active grant was not found.');
    }
}
