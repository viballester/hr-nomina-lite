<?php

namespace App\Services;

use Carbon\CarbonInterface;

class LeaveRequestService
{
    public function countWorkingDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $days = 0;
        $current = $start->copy();

        while($current->lte($end)) {
            if(!$current->isWeekend()) {
                $days++;
            }

            $current->addDay();
        }
        return $days;
    }
}