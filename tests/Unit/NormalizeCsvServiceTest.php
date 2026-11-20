<?php

namespace Tests\Unit;

use App\Enums\BusinessType;
use App\Enums\PropertyStatus;
use App\Services\NormalizeCsvService;
use PHPUnit\Framework\TestCase;

class NormalizeCsvServiceTest extends TestCase
{
    private NormalizeCsvService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NormalizeCsvService();
    }

    public function test_it_parses_csv_into_normalized_structure(): void
    {
        $csvPath = __DIR__ . '/../../carteira_sabrina_nov2026.csv';
        $items = $this->service->parse($csvPath);

        $this->assertCount(18, $items);

        $first = $items[0];
        $this->assertEquals('IMV-001', $first['codigo']);
        $this->assertEquals('Apartamento', $first['tipo']);
        $this->assertEquals('São Paulo', $first['cidade']);
        $this->assertEquals(BusinessType::Venda->value, $first['negocio']);
        $this->assertEquals(PropertyStatus::Vendido->value, $first['status']);
        $this->assertEquals(980000.0, $first['preco_anunciado']);
        $this->assertEquals(950000.0, $first['preco_fechado']);
        $this->assertEquals('2026-11-05', $first['data_fechamento']);
    }
}
