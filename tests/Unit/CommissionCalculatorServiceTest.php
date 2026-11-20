<?php

namespace Tests\Unit;

use App\Services\CommissionCalculatorService;
use App\Services\CsvNormalizerService;
use PHPUnit\Framework\TestCase;

class CommissionCalculatorServiceTest extends TestCase
{
    private CsvNormalizerService $normalizer;
    private CommissionCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new CsvNormalizerService();
        $this->calculator = new CommissionCalculatorService();
    }

    public function test_it_calculates_november_2026_commissions_accurately(): void
    {
        $csvPath = __DIR__ . '/../../carteira_sabrina_nov2026.csv';
        $items = $this->normalizer->parse($csvPath);

        $this->assertCount(18, $items);

        $report = $this->calculator->calculateMonth($items, '2026-11');

        $totals = $report['totals'];

        // Vendas no ciclo (21/10 a 20/11):
        // IMV-001 (05/11): 6% de 950.000 = 57.000
        // IMV-013 (20/11): 6% de 400.000 = 24.000
        // Total Vendas = 81.000
        $this->assertEquals(81000.00, $totals['total_vendas']);

        // Aluguéis Novos no ciclo:
        // IMV-003 (01/11): 100% de 2.800 = 2.800
        // IMV-011 (15/11): 100% de 3.100 = 3.100
        // Total Aluguel Novo = 5.900
        $this->assertEquals(5900.00, $totals['total_aluguel_novo']);

        // Aluguéis Recorrentes no ciclo (10%):
        // IMV-004: 10% de 3.500 = 350
        // IMV-008: 10% de 1.600 = 160
        // IMV-014: 10% de 5.000 = 500
        // Total Aluguel Recorrente = 1.010
        $this->assertEquals(1010.00, $totals['total_aluguel_recorrente']);

        // Total Oficial Garantido de Novembro:
        // 81.000 + 5.900 + 1.010 = 87.910,00
        $this->assertEquals(87910.00, $totals['total_garantido']);

        // Inadimplência / Pendência:
        // IMV-017 (inquilino atrasou o aluguel de novembro): 450,00 pendente
        $this->assertEquals(450.00, $totals['total_pendente']);

        // Pipeline / Em negociação:
        // IMV-006 (Venda 720k): 6% = 43.200
        // IMV-010 (Aluguel 4.2k): 100% = 4.200
        // IMV-016 (Venda 380k): 6% = 22.800
        // Total Pipeline = 70.200,00
        $this->assertEquals(70200.00, $totals['total_pipeline']);
    }

    public function test_boundary_conditions_and_cutoff_at_day_20(): void
    {
        $csvPath = __DIR__ . '/../../carteira_sabrina_nov2026.csv';
        $items = $this->normalizer->parse($csvPath);

        $report = $this->calculator->calculateMonth($items, '2026-11');
        $byCode = collect($report['items'])->keyBy('codigo');

        // IMV-007: Vendido em 18/10/2026 (anterior a 21/10/2026) -> R$ 0 no ciclo de novembro
        $this->assertEquals(0.0, $byCode['IMV-007']['comissao_oficial']);
        $this->assertStringContainsString('anterior ao ciclo', $byCode['IMV-007']['regra_aplicada']);

        // IMV-013: Vendido em 20/11/2026 (dia limite do corte) -> R$ 24.000 no ciclo de novembro
        $this->assertEquals(24000.0, $byCode['IMV-013']['comissao_oficial']);
        $this->assertEquals('venda_fechada', $byCode['IMV-013']['categoria']);

        // IMV-018: Reservado com escritura para 10/12 -> R$ 0 em novembro
        $this->assertEquals(0.0, $byCode['IMV-018']['comissao_oficial']);
        $this->assertEquals('reservado', $byCode['IMV-018']['categoria']);
    }
}
