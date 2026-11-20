<?php

namespace Tests\Unit;

use App\Helpers\MoneyHelper;
use PHPUnit\Framework\TestCase;

class MoneyHelperTest extends TestCase
{
    public function test_it_parses_various_money_string_formats(): void
    {
        // "mil" notation
        $this->assertEquals(720000.0, MoneyHelper::parseMoneyFromString('720 mil'));
        $this->assertEquals(410000.0, MoneyHelper::parseMoneyFromString('410 mil'));
        $this->assertEquals(400000.0, MoneyHelper::parseMoneyFromString('400 mil'));

        // Brazilian comma & dot
        $this->assertEquals(980000.0, MoneyHelper::parseMoneyFromString('R$ 980.000,00'));
        $this->assertEquals(950000.0, MoneyHelper::parseMoneyFromString('R$ 950.000,00'));
        $this->assertEquals(1900.0, MoneyHelper::parseMoneyFromString('1.900,00'));
        $this->assertEquals(4500.0, MoneyHelper::parseMoneyFromString('4.500,00'));

        // "/mês" notation
        $this->assertEquals(3500.0, MoneyHelper::parseMoneyFromString('R$3.500/mês'));

        // Multiple dots without comma
        $this->assertEquals(1150000.0, MoneyHelper::parseMoneyFromString('1.150.000'));
        $this->assertEquals(2300000.0, MoneyHelper::parseMoneyFromString('2.300.000'));

        // Single dot thousand
        $this->assertEquals(2800.0, MoneyHelper::parseMoneyFromString('2.800'));

        // Plain integers
        $this->assertEquals(1450000.0, MoneyHelper::parseMoneyFromString('1450000'));
        $this->assertEquals(1600.0, MoneyHelper::parseMoneyFromString('1600'));
        $this->assertEquals(3100.0, MoneyHelper::parseMoneyFromString('3100'));
        $this->assertEquals(2400.0, MoneyHelper::parseMoneyFromString('2400'));

        // Null & empty
        $this->assertNull(MoneyHelper::parseMoneyFromString(null));
        $this->assertNull(MoneyHelper::parseMoneyFromString(''));
    }
}
