<?php

namespace Tests\Unit;

use App\Services\CsvNormalizerService;
use PHPUnit\Framework\TestCase;

class CsvNormalizerServiceTest extends TestCase
{
    private CsvNormalizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CsvNormalizerService();
    }

    public function test_it_parses_messy_currency_formats(): void
    {
        // "mil" notation
        $this->assertEquals(720000.0, $this->service->parseMoney('720 mil'));
        $this->assertEquals(410000.0, $this->service->parseMoney('410 mil'));
        $this->assertEquals(400000.0, $this->service->parseMoney('400 mil'));

        // Brazilian comma & dot
        $this->assertEquals(980000.0, $this->service->parseMoney('R$ 980.000,00'));
        $this->assertEquals(950000.0, $this->service->parseMoney('R$ 950.000,00'));
        $this->assertEquals(1900.0, $this->service->parseMoney('1.900,00'));
        $this->assertEquals(4500.0, $this->service->parseMoney('4.500,00'));

        // "/mês" notation
        $this->assertEquals(3500.0, $this->service->parseMoney('R$3.500/mês'));

        // Multiple dots without comma
        $this->assertEquals(1150000.0, $this->service->parseMoney('1.150.000'));
        $this->assertEquals(2300000.0, $this->service->parseMoney('2.300.000'));

        // Single dot thousand
        $this->assertEquals(2800.0, $this->service->parseMoney('2.800'));

        // Plain integers
        $this->assertEquals(1450000.0, $this->service->parseMoney('1450000'));
        $this->assertEquals(1600.0, $this->service->parseMoney('1600'));
        $this->assertEquals(3100.0, $this->service->parseMoney('3100'));
        $this->assertEquals(2400.0, $this->service->parseMoney('2400'));

        // Null & empty
        $this->assertNull($this->service->parseMoney(null));
        $this->assertNull($this->service->parseMoney(''));
    }

    public function test_it_normalizes_city_names(): void
    {
        $this->assertEquals('Campinas', $this->service->normalizeCity('campinas'));
        $this->assertEquals('Campinas', $this->service->normalizeCity('CAMPINAS'));
        $this->assertEquals('Sao Paulo', $this->service->normalizeCity('sao paulo'));
        $this->assertEquals('São Paulo', $this->service->normalizeCity('são paulo'));
        $this->assertEquals('Ribeirão Preto', $this->service->normalizeCity('ribeirão preto'));
        $this->assertEquals('Santos', $this->service->normalizeCity('santos'));
        $this->assertEquals('', $this->service->normalizeCity('   '));
    }
}
