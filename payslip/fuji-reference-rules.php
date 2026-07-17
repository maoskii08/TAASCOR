<?php

declare(strict_types=1);

function fuji_reference_late_undertime_hours($lateMinutes, $undertimeMinutes): float
{
    return ((float)$lateMinutes + (float)$undertimeMinutes) / 60;
}

function fuji_reference_late_undertime_amount($lateAmount, $undertimeAmount): float
{
    return (float)$lateAmount + (float)$undertimeAmount;
}
