<?php

namespace Tests\Unit;

use App\Helpers\DateHelper;
use PHPUnit\Framework\TestCase;

class DateHelperTest extends TestCase
{
    public function test_it_parses_dates_correctly(): void
    {
        $d1 = DateHelper::parseDateFromString('05/11/2026');
        $this->assertNotNull($d1);
        $this->assertEquals('2026-11-05', $d1->format('Y-m-d'));

        $d2 = DateHelper::parseDateFromString('2026-11-20');
        $this->assertNotNull($d2);
        $this->assertEquals('2026-11-20', $d2->format('Y-m-d'));

        $d3 = DateHelper::parseDateFromString('01-10-2026');
        $this->assertNotNull($d3);
        $this->assertEquals('2026-10-01', $d3->format('Y-m-d'));

        $this->assertNull(DateHelper::parseDateFromString(null));
        $this->assertNull(DateHelper::parseDateFromString(''));
        $this->assertNull(DateHelper::parseDateFromString('invalid-date'));
    }
}
