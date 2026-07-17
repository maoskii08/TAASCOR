<?php

declare(strict_types=1);

function payslip_positive_int_filter(array $query, string $key): ?int
{
    if (!array_key_exists($key, $query)) {
        return null;
    }
    $value = filter_var(
        $query[$key],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($value === false) {
        throw new InvalidArgumentException('Invalid positive integer filter.');
    }
    return (int)$value;
}
