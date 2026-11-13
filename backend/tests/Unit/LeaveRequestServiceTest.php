<?php

namespace Tests\Unit;

use App\Services\LeaveRequestService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LeaveRequestServiceTest extends TestCase
{
    public static function rangos(): array
    {
        return [
            'un lunes'                 => ['2026-11-16', '2026-11-16', 1],
            'semana completa L-V'      => ['2026-11-16', '2026-11-20', 5],
            'sábado y domingo'         => ['2026-11-21', '2026-11-22', 0],
            'viernes a lunes'          => ['2026-11-20', '2026-11-23', 2],
            'dos semanas L-D'          => ['2026-11-16', '2026-11-29', 10],
        ];
    }

    #[DataProvider('rangos')]
    public function test_cuenta_dias_laborables(string $start, string $end, int $expected): void
    {
        $service = new LeaveRequestService();

        $this->assertSame($expected, $service->countWorkingDays(Carbon::parse($start), Carbon::parse($end)));
    }
}