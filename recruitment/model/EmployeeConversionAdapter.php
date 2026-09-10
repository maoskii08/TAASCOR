<?php

declare(strict_types=1);

interface EmployeeConversionAdapter
{
    /** @return array<string, mixed> */
    public function normalizePayload(array $employeePayload): array;

    /** @return array{clear:bool,evidence:array<string, mixed>} */
    public function duplicateCheck(array $employeePayload): array;

    public function createEmployee(array $employeePayload): string;

    /** @return array<string, mixed>|null */
    public function employeeSnapshot(string $employeeReference): ?array;
}
