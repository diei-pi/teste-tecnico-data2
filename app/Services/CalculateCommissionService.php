<?php

namespace App\Services;

use App\Enums\BusinessType;
use App\Enums\CommissionCategory;
use App\Enums\PropertyStatus;
use Carbon\Carbon;

class CalculateCommissionService
{
    public const SALE_COMMISSION_RATE = 0.06;
    public const NEW_RENT_COMMISSION_RATE = 1.0;
    public const RECURRING_RENT_COMMISSION_RATE = 0.10;
    public const CYCLE_CUTOFF_DAY = 20;
    public const CYCLE_START_DAY = 21;

    /**
     * Calculate monthly reports for all discovered months from the normalized items.
     */
    public function calculateAll(array $normalizedItems): array
    {
        $months = $this->discoverMonths($normalizedItems);

        $resultsByMonth = [];
        foreach ($months as $monthKey) {
            $resultsByMonth[$monthKey] = $this->calculateMonth($normalizedItems, $monthKey);
        }

        return [
            'available_months' => $months,
            'default_month' => in_array('2026-11', $months) ? '2026-11' : ($months[0] ?? null),
            'results_by_month' => $resultsByMonth,
            'total_items_in_portfolio' => count($normalizedItems),
        ];
    }

    /**
     * Calculate commission and categorization for a specific month (YYYY-MM).
     * Cycle: 21st of (M-1) to 20th of M.
     */
    public function calculateMonth(array $items, string $yearMonth): array
    {
        [$year, $month] = explode('-', $yearMonth);
        $currentMonthDate = Carbon::createFromDate((int)$year, (int)$month, 1)->startOfDay();

        // Cycle starts on 21st of previous month
        $cycleStart = $currentMonthDate->copy()->subMonth()->day(self::CYCLE_START_DAY)->startOfDay();
        // Cycle ends on 20th of current month at 23:59:59
        $cycleEnd = $currentMonthDate->copy()->day(self::CYCLE_CUTOFF_DAY)->endOfDay();

        $processedItems = [];
        $totalGarantido = 0.0;
        $totalVendas = 0.0;
        $totalAluguelNovo = 0.0;
        $totalAluguelRecorrente = 0.0;
        $totalPipeline = 0.0;
        $totalPendente = 0.0;

        foreach ($items as $item) {
            $processed = $this->evaluateItemForMonth($item, $cycleStart, $cycleEnd, $yearMonth);
            $processedItems[] = $processed;

            $totalGarantido += $processed['comissao_oficial'];

            if ($processed['categoria'] === CommissionCategory::VendaFechada->value) {
                $totalVendas += $processed['comissao_oficial'];
            }

            if ($processed['categoria'] === CommissionCategory::AluguelNovo->value) {
                $totalAluguelNovo += $processed['comissao_oficial'];
            }

            if ($processed['categoria'] === CommissionCategory::AluguelRecorrente->value) {
                $totalAluguelRecorrente += $processed['comissao_oficial'];
            }

            $totalPipeline += $processed['comissao_pipeline'];
            $totalPendente += $processed['comissao_pendente'];
        }

        $previewItems = count($processedItems) > 10000
            ? array_slice($processedItems, 0, 10000)
            : $processedItems;

        return [
            'month_key' => $yearMonth,
            'month_name' => $this->getMonthLabel($yearMonth),
            'cycle_start' => $cycleStart->format('d/m/Y'),
            'cycle_end' => $cycleEnd->format('d/m/Y'),
            'totals' => [
                'total_garantido' => round($totalGarantido, 2),
                'total_vendas' => round($totalVendas, 2),
                'total_aluguel_novo' => round($totalAluguelNovo, 2),
                'total_aluguel_recorrente' => round($totalAluguelRecorrente, 2),
                'total_pipeline' => round($totalPipeline, 2),
                'total_pendente' => round($totalPendente, 2),
                'imoveis_count' => count($items),
            ],
            'items' => $previewItems,
            'total_items_count' => count($processedItems),
            'is_truncated' => count($processedItems) > 10000,
        ];
    }

    /**
     * Evaluate single property for a given monthly cycle.
     */
    private function evaluateItemForMonth(array $item, Carbon $cycleStart, Carbon $cycleEnd, string $yearMonth): array
    {
        $businessType = $item['negocio_enum'] ?? BusinessType::tryFrom($item['negocio'] ?? '') ?? BusinessType::Outro;
        $status = $item['status_enum'] ?? PropertyStatus::tryFrom($item['status'] ?? '') ?? PropertyStatus::Outro;

        $dataFechamento = !empty($item['data_fechamento']) ? Carbon::parse($item['data_fechamento']) : null;
        $inicioContrato = !empty($item['inicio_contrato']) ? Carbon::parse($item['inicio_contrato']) : null;
        $obs = mb_strtolower($item['observacao'] ?? '', 'UTF-8');

        if ($businessType === BusinessType::Venda) {
            return $this->evaluateSale($item, $status, $dataFechamento, $cycleStart, $cycleEnd);
        }

        if ($businessType === BusinessType::Aluguel) {
            return $this->evaluateRent($item, $status, $inicioContrato, $cycleStart, $cycleEnd, $obs);
        }

        return $this->buildItemResult(
            $item,
            precoBase: $item['preco_anunciado'] ?? 0.0,
            regra: 'Operação não identificada',
            category: CommissionCategory::Estoque
        );
    }

    /**
     * Evaluate sales rules using early returns.
     */
    private function evaluateSale(
        array $item,
        PropertyStatus $status,
        ?Carbon $dataFechamento,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): array {
        $precoAnunciado = $item['preco_anunciado'] ?? 0.0;
        $precoFechado = $item['preco_fechado'] ?? $precoAnunciado;

        if ($status === PropertyStatus::Vendido) {
            if ($dataFechamento?->betweenIncluded($cycleStart, $cycleEnd)) {
                $commission = $precoFechado * self::SALE_COMMISSION_RATE;
                return $this->buildItemResult(
                    $item,
                    precoBase: $precoFechado,
                    oficial: $commission,
                    regra: "Venda fechada no ciclo ({$dataFechamento->format('d/m/Y')}) - 6% da escritura",
                    category: CommissionCategory::VendaFechada
                );
            }

            if ($dataFechamento?->lt($cycleStart)) {
                return $this->buildItemResult(
                    $item,
                    precoBase: $precoFechado,
                    regra: "Venda anterior ao ciclo ({$dataFechamento->format('d/m/Y')}) - Já apurada anteriormente",
                    category: CommissionCategory::Passado
                );
            }

            return $this->buildItemResult(
                $item,
                precoBase: $precoFechado,
                regra: 'Venda fora do ciclo vigente',
                category: CommissionCategory::Futuro
            );
        }

        if ($status === PropertyStatus::EmNegociacao) {
            $pipelineCommission = $precoAnunciado * self::SALE_COMMISSION_RATE;
            return $this->buildItemResult(
                $item,
                precoBase: $precoAnunciado,
                pipeline: $pipelineCommission,
                regra: 'Em negociação (previsão de 6% sobre anunciado)',
                category: CommissionCategory::Pipeline
            );
        }

        if ($status === PropertyStatus::Reservado) {
            return $this->buildItemResult(
                $item,
                precoBase: $precoAnunciado,
                regra: 'Reservado (sinal pago - comissão diferida para mês da escritura)',
                category: CommissionCategory::Reservado
            );
        }

        if ($status === PropertyStatus::Suspenso) {
            return $this->buildItemResult(
                $item,
                precoBase: $precoAnunciado,
                regra: 'Imóvel suspenso pelo proprietário',
                category: CommissionCategory::Suspenso
            );
        }

        return $this->buildItemResult(
            $item,
            precoBase: $precoAnunciado,
            regra: 'Disponível para venda',
            category: CommissionCategory::Estoque
        );
    }

    /**
     * Evaluate rent rules using early returns.
     */
    private function evaluateRent(
        array $item,
        PropertyStatus $status,
        ?Carbon $inicioContrato,
        Carbon $cycleStart,
        Carbon $cycleEnd,
        string $obs
    ): array {
        $precoBase = $item['preco_fechado'] ?? $item['preco_anunciado'] ?? 0.0;
        $isAtrasado = str_contains($obs, 'atrasou') || str_contains($obs, 'inadimpl');

        if ($status === PropertyStatus::Alugado) {
            if ($inicioContrato?->betweenIncluded($cycleStart, $cycleEnd)) {
                if ($isAtrasado) {
                    return $this->buildItemResult(
                        $item,
                        precoBase: $precoBase,
                        pendente: $precoBase * self::NEW_RENT_COMMISSION_RATE,
                        regra: '1º Mês de aluguel (100%) - Pagamento em atraso pelo inquilino',
                        category: CommissionCategory::Pendente
                    );
                }

                return $this->buildItemResult(
                    $item,
                    precoBase: $precoBase,
                    oficial: $precoBase * self::NEW_RENT_COMMISSION_RATE,
                    regra: 'Aluguel novo no ciclo (1º Mês: 100%)',
                    category: CommissionCategory::AluguelNovo
                );
            }

            if ($inicioContrato?->lt($cycleStart)) {
                $recurrentCommission = $precoBase * self::RECURRING_RENT_COMMISSION_RATE;
                if ($isAtrasado) {
                    return $this->buildItemResult(
                        $item,
                        precoBase: $precoBase,
                        pendente: $recurrentCommission,
                        regra: 'Aluguel recorrente (10%) - Pagamento em atraso pelo inquilino',
                        category: CommissionCategory::Pendente
                    );
                }

                return $this->buildItemResult(
                    $item,
                    precoBase: $precoBase,
                    oficial: $recurrentCommission,
                    regra: 'Aluguel recorrente (Mês 2+: 10%)',
                    category: CommissionCategory::AluguelRecorrente
                );
            }

            return $this->buildItemResult(
                $item,
                precoBase: $precoBase,
                regra: 'Contrato com início futuro',
                category: CommissionCategory::Futuro
            );
        }

        if ($status === PropertyStatus::EmNegociacao) {
            return $this->buildItemResult(
                $item,
                precoBase: $precoBase,
                pipeline: $precoBase * self::NEW_RENT_COMMISSION_RATE,
                regra: 'Em negociação (previsão de 100% no 1º mês)',
                category: CommissionCategory::Pipeline
            );
        }

        return $this->buildItemResult(
            $item,
            precoBase: $precoBase,
            regra: 'Disponível para locação',
            category: CommissionCategory::Estoque
        );
    }

    /**
     * Build unified item result structure.
     */
    private function buildItemResult(
        array $item,
        float $precoBase,
        float $oficial = 0.0,
        float $pipeline = 0.0,
        float $pendente = 0.0,
        string $regra = 'Sem comissão no período',
        CommissionCategory $category = CommissionCategory::Estoque
    ): array {
        return array_merge($item, [
            'preco_base_calculo' => round($precoBase, 2),
            'comissao_oficial' => round($oficial, 2),
            'comissao_pipeline' => round($pipeline, 2),
            'comissao_pendente' => round($pendente, 2),
            'regra_aplicada' => $regra,
            'categoria' => $category->value,
            'badge_class' => $category->badgeClass(),
        ]);
    }

    /**
     * Discover all relevant months from properties data.
     */
    private function discoverMonths(array $items): array
    {
        $months = ['2026-11']; // Always ensure target month is available

        foreach ($items as $item) {
            foreach (['data_fechamento', 'inicio_contrato'] as $field) {
                if (!empty($item[$field])) {
                    $dt = Carbon::parse($item[$field]);
                    // If date is > 20th of month, it rolls to next month!
                    $monthBelonging = $dt->day > self::CYCLE_CUTOFF_DAY
                        ? $dt->copy()->addMonth()->format('Y-m')
                        : $dt->format('Y-m');
                    $months[] = $monthBelonging;
                }
            }

            // Also check observations for date hints like "escritura dia 10/12"
            $obs = $item['observacao'] ?? '';
            if (preg_match('/(\d{1,2})\/(\d{1,2})/', $obs, $matches)) {
                $m = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $months[] = "2026-{$m}";
            }
        }

        $unique = array_unique($months);
        rsort($unique); // Descending order
        return array_values($unique);
    }

    /**
     * Format Month label in Portuguese.
     */
    private function getMonthLabel(string $yearMonth): string
    {
        [$y, $m] = explode('-', $yearMonth);
        $names = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
            '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
            '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
            '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        $label = $names[$m] ?? $m;
        return "{$label} / {$y}";
    }
}
