<?php

namespace Database\Seeders;

use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Illuminate\Database\Seeder;

/** Dos meses cerrados y el actual en borrador. Se puede lanzar solo y repetir sin duplicar. */
class PayrollSeeder extends Seeder
{
    public function run(PayrollService $service): void
    {
        foreach ([2, 1, 0] as $monthsAgo) {
            $date = today()->startOfMonth()->subMonths($monthsAgo);
            $period = PayrollPeriod::firstOrCreate(['year' => $date->year, 'month' => $date->month])->refresh();

            if ($period->status === 'closed') {
                continue;
            }

            if ($service->generate($period) > 0 && $monthsAgo > 0) {
                $service->close($period);
            }
        }
    }
}
