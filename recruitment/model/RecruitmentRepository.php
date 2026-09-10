<?php

declare(strict_types=1);

final class RecruitmentRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function publishedJobs(?int $limit = null): array
    {
        $limit = $limit === null ? 100 : max(1, min($limit, 100));
        $sql = "SELECT public_id, slug, title, location_label, work_arrangement,
                       employment_type, summary, description_html, requirements_html,
                       hiring_process_html, content_version, opens_at, closes_at, updated_at
                  FROM recruitment_jobs
                 WHERE publication_status = 'published'
                   AND (opens_at IS NULL OR opens_at <= UTC_TIMESTAMP())
                   AND (closes_at IS NULL OR closes_at > UTC_TIMESTAMP())
                 ORDER BY published_at DESC, job_id DESC
                 LIMIT {$limit}";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function publishedJob(string $publicId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT public_id, slug, title, location_label, work_arrangement,
                    employment_type, summary, description_html, requirements_html,
                    hiring_process_html, content_version, opens_at, closes_at, updated_at
               FROM recruitment_jobs
              WHERE public_id = :public_id
                AND publication_status = 'published'
                AND (opens_at IS NULL OR opens_at <= UTC_TIMESTAMP())
                AND (closes_at IS NULL OR closes_at > UTC_TIMESTAMP())
              LIMIT 1"
        );
        $statement->execute(['public_id' => trim($publicId)]);
        $job = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($job) ? $job : null;
    }

    /** @return array<string, int> */
    public function staffSummary(): array
    {
        $sql = "SELECT
                    SUM(status = 'pending_approval') AS pending_requisitions,
                    SUM(status = 'approved') AS approved_requisitions
                  FROM recruitment_requisitions";
        $requisitions = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        $jobSql = "SELECT SUM(publication_status = 'published') AS published_jobs
                     FROM recruitment_jobs";
        $jobs = $this->db->query($jobSql)->fetch(PDO::FETCH_ASSOC) ?: [];

        $applicationSql = "SELECT
                              SUM(current_status IN ('submitted', 'reviewing', 'shortlisted', 'interview', 'requirements')) AS active_applications,
                              SUM(current_status = 'ready_for_conversion') AS ready_for_conversion
                             FROM recruitment_applications";
        $applications = $this->db->query($applicationSql)->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'pending_requisitions' => (int)($requisitions['pending_requisitions'] ?? 0),
            'approved_requisitions' => (int)($requisitions['approved_requisitions'] ?? 0),
            'published_jobs' => (int)($jobs['published_jobs'] ?? 0),
            'active_applications' => (int)($applications['active_applications'] ?? 0),
            'ready_for_conversion' => (int)($applications['ready_for_conversion'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function requisitionQueue(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = "SELECT requisition_code,
                       title,
                       headcount,
                       COALESCE(DATE_FORMAT(target_start_date, '%Y-%m-%d'), 'Not set') AS target_start_date,
                       status
                  FROM recruitment_requisitions
                 WHERE status IN ('draft', 'pending_approval', 'approved', 'rejected')
                 ORDER BY
                       FIELD(status, 'pending_approval', 'approved', 'draft', 'rejected'),
                       target_start_date IS NULL,
                       target_start_date,
                       requisition_id DESC
                 LIMIT {$limit}";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function jobQueue(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = "SELECT j.title,
                       r.requisition_code,
                       j.location_label,
                       CASE
                           WHEN j.opens_at IS NULL AND j.closes_at IS NULL THEN 'Not scheduled'
                           ELSE CONCAT(
                               COALESCE(DATE_FORMAT(j.opens_at, '%Y-%m-%d'), 'Open'),
                               ' to ',
                               COALESCE(DATE_FORMAT(j.closes_at, '%Y-%m-%d'), 'No close')
                           )
                       END AS publication_window,
                       j.publication_status
                  FROM recruitment_jobs j
                  JOIN recruitment_requisitions r ON r.requisition_id = j.requisition_id
                 ORDER BY j.updated_at DESC, j.job_id DESC
                 LIMIT {$limit}";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function applicationPipeline(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = "SELECT a.public_id AS application_reference,
                       j.title,
                       a.current_status,
                       CONCAT(TIMESTAMPDIFF(DAY, a.updated_at, UTC_TIMESTAMP()), ' days') AS stage_age,
                       DATE_FORMAT(a.updated_at, '%Y-%m-%d %H:%i UTC') AS updated_at
                  FROM recruitment_applications a
                  JOIN recruitment_jobs j ON j.job_id = a.job_id
                 WHERE a.current_status NOT IN ('converted', 'declined', 'withdrawn', 'offer_declined')
                 ORDER BY a.updated_at, a.application_id
                 LIMIT {$limit}";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function exceptionQueue(int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $sql = "SELECT exception_type,
                       record_reference,
                       owner_reference,
                       age_label,
                       priority
                  FROM (
                    SELECT 'Target date passed' AS exception_type,
                           r.requisition_code AS record_reference,
                           COALESCE(r.recruitment_owner_username, r.hiring_owner_username) AS owner_reference,
                           CONCAT(DATEDIFF(UTC_DATE(), r.target_start_date), ' days') AS age_label,
                           'Review' AS priority,
                           r.target_start_date AS sort_date
                      FROM recruitment_requisitions r
                     WHERE r.target_start_date < UTC_DATE()
                       AND r.status IN ('draft', 'pending_approval', 'approved')
                    UNION ALL
                    SELECT 'Application stage aging' AS exception_type,
                           a.public_id AS record_reference,
                           'Recruitment queue' AS owner_reference,
                           CONCAT(TIMESTAMPDIFF(DAY, a.updated_at, UTC_TIMESTAMP()), ' days') AS age_label,
                           'Review' AS priority,
                           DATE(a.updated_at) AS sort_date
                      FROM recruitment_applications a
                     WHERE a.current_status IN ('submitted', 'reviewing', 'shortlisted', 'interview', 'requirements')
                       AND a.updated_at < UTC_TIMESTAMP() - INTERVAL 7 DAY
                  ) exceptions
                 ORDER BY sort_date
                 LIMIT {$limit}";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function interviewQueue(int $limit = 50): array
    {
        $limit=max(1,min($limit,100));
        return $this->db->query("SELECT i.public_id,j.title,i.starts_at_utc,i.timezone_name,i.status FROM recruitment_interviews i JOIN recruitment_applications a ON a.application_id=i.application_id JOIN recruitment_jobs j ON j.job_id=a.job_id ORDER BY i.starts_at_utc LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function offerQueue(int $limit = 50): array
    {
        $limit=max(1,min($limit,100));
        return $this->db->query("SELECT o.public_id,j.title,o.current_version,COALESCE(DATE_FORMAT(o.expires_at,'%Y-%m-%d'),'No expiry'),o.status FROM recruitment_offers o JOIN recruitment_applications a ON a.application_id=o.application_id JOIN recruitment_jobs j ON j.job_id=a.job_id ORDER BY o.updated_at DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function onboardingQueue(int $limit = 50): array
    {
        $limit=max(1,min($limit,100));
        return $this->db->query("SELECT c.public_id,j.title,c.owner_username,COALESCE(DATE_FORMAT(c.target_start_date,'%Y-%m-%d'),'Not set'),c.status FROM recruitment_onboarding_cases c JOIN recruitment_applications a ON a.application_id=c.application_id JOIN recruitment_jobs j ON j.job_id=a.job_id ORDER BY c.updated_at DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function conversionQueue(int $limit = 50): array
    {
        $limit=max(1,min($limit,100));
        return $this->db->query("SELECT r.public_id,c.public_id AS onboarding_case,r.duplicate_check_status,COALESCE(r.approved_by_username,'Awaiting review'),r.status FROM recruitment_employee_conversion_requests r JOIN recruitment_onboarding_cases c ON c.onboarding_case_id=r.onboarding_case_id ORDER BY r.updated_at DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function reportRows(): array
    {
        $summary=$this->staffSummary();
        return [
            ['Open requisitions','Approved jobs','Active applications','Ready to convert','Snapshot'],
            [$summary['pending_requisitions']+$summary['approved_requisitions'],$summary['published_jobs'],$summary['active_applications'],$summary['ready_for_conversion'],gmdate('Y-m-d H:i').' UTC'],
        ];
    }
}
