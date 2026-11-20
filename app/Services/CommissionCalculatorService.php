<?php

namespace App\Services;

use App\Enums\BusinessType;
use App\Enums\PropertyStatus;
use Carbon\Carbon;

class CommissionCalculatorService
{
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
        $cycleStart = $currentMonthDate->copy()->subMonth()->day(21)->startOfDay();
        // Cycle ends on 20th of current month at 23:59:59
        $cycleEnd = $currentMonthDate->copy()->day(20)->endOfDay();

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
            if ($processed['categoria'] === 'venda_fechada') {
                $totalVendas += $processed['comissao_oficial'];
            } elseif ($processed['categoria'] === 'aluguel_novo') {
                $totalAluguelNovo += $processed['comissao_oficial'];
            } elseif ($processed['categoria'] === 'aluguel_recorrente') {
                $totalAluguelRecorrente += $processed['comissao_oficial'];
            }

            $totalPipeline += $processed['comissao_pipeline'];
            $totalPendente += $processed['comissao_pendente'];
        }

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
            'items' => $processedItems,
        ];
    }

    /**
     * Evaluate single property for a given monthly cycle.
     */
    protected function evaluateItemForMonth(array $item, Carbon $cycleStart, Carbon $cycleEnd, string $yearMonth): array
    {
        $negocio = $item['negocio_enum'] ?? BusinessType::tryFrom($item['negocio'] ?? '') ?? $item['negocio'];
        $status = $item['status_enum'] ?? PropertyStatus::tryFrom($item['status'] ?? '') ?? $item['status'];
        $obs = mb_strtolower($item['observacao'] ?? '', 'UTF-8');

        $dataFechamento = !empty($item['data_fechamento']) ? Carbon::parse($item['data_fechamento']) : null;
        $inicioContrato = !empty($item['inicio_contrato']) ? Carbon::parse($item['inicio_contrato']) : null;

        $comissaoOficial = 0.0;
        $comissaoPipeline = 0.0;
        $comissaoPendente = 0.0;
        $regra = 'Sem comissão no período';
        $categoria = 'estoque'; // venda_fechada, aluguel_novo, aluguel_recorrente, pipeline, pendente, estoque, suspenso
        $badgeClass = 'bg-gray-100 text-gray-800';

        $isVenda = ($negocio === BusinessType::Venda || $negocio === 'Venda');
        $isAluguel = ($negocio === BusinessType::Aluguel || $negocio === 'Aluguel');

        $isVendido = ($status === PropertyStatus::Vendido || $status === 'Vendido');
        $isAlugado = ($status === PropertyStatus::Alugado || $status === 'Alugado');
        $isEmNegociacao = ($status === PropertyStatus::EmNegociacao || $status === 'Em negociação');
        $isReservado = ($status === PropertyStatus::Reservado || $status === 'Reservado');
        $isSuspenso = ($status === PropertyStatus::Suspenso || $status === 'Suspenso');

        // 1. VENDA
        if ($isVenda) {
            if ($isVendido) {
                $precoBase = $item['preco_fechado'] ?? $item['preco_anunciado'] ?? 0.0;

                if ($dataFechamento && $dataFechamento->betweenIncluded($cycleStart, $cycleEnd)) {
                    // Closed inside cycle!
                    $comissaoOficial = $precoBase * 0.06;
                    $regra = "Venda fechada no ciclo ({$dataFechamento->format('d/m/Y')}) - 6% da escritura";
                    $categoria = 'venda_fechada';
                    $badgeClass = 'bg-emerald-100 text-emerald-800';
                } elseif ($dataFechamento && $dataFechamento->lt($cycleStart)) {
                    $regra = "Venda anterior ao ciclo ({$dataFechamento->format('d/m/Y')}) - Já apurada anteriormente";
                    $categoria = 'passado';
                    $badgeClass = 'bg-gray-100 text-gray-500';
                } else {
                    $regra = "Venda fora do ciclo vigente";
                    $categoria = 'futuro';
                    $badgeClass = 'bg-blue-50 text-blue-600';
                }
            } elseif ($isEmNegociacao) {
                $precoBase = $item['preco_anunciado'] ?? 0.0;
                $comissaoPipeline = $precoBase * 0.06;
                $regra = 'Em negociação (previsão de 6% sobre anunciado)';
                $categoria = 'pipeline';
                $badgeClass = 'bg-amber-100 text-amber-800';
            } elseif ($isReservado) {
                // If reserved with future deed noted
                $precoBase = $item['preco_anunciado'] ?? 0.0;
                $regra = 'Reservado (sinal pago - comissão diferida para mês da escritura)';
                $categoria = 'reservado';
                $badgeClass = 'bg-purple-100 text-purple-800';
            } elseif ($isSuspenso) {
                $regra = 'Imóvel suspenso pelo proprietário';
                $categoria = 'suspenso';
                $badgeClass = 'bg-red-50 text-red-600';
            } else {
                $regra = 'Disponível para venda';
                $categoria = 'estoque';
                $badgeClass = 'bg-gray-100 text-gray-600';
            }
        }

        // 2. ALUGUEL
        elseif ($isAluguel) {
            $precoBase = $item['preco_fechado'] ?? $item['preco_anunciado'] ?? 0.0;

            if ($isAlugado) {
                // Check for delayed rent payment (inadimplência / atraso)
                $isAtrasado = str_contains($obs, 'atrasou') || str_contains($obs, 'inadimpl');

                if ($inicioContrato && $inicioContrato->betweenIncluded($cycleStart, $cycleEnd)) {
                    // Month 1 inside cycle: 100%
                    if ($isAtrasado) {
                        $comissaoPendente = $precoBase;
                        $regra = '1º Mês de aluguel (100%) - Pagamento em atraso pelo inquilino';
                        $categoria = 'pendente';
                        $badgeClass = 'bg-rose-100 text-rose-800';
                    } else {
                        $comissaoOficial = $precoBase;
                        $regra = 'Aluguel novo no ciclo (1º Mês: 100%)';
                        $categoria = 'aluguel_novo';
                        $badgeClass = 'bg-blue-100 text-blue-800';
                    }
                } elseif ($inicioContrato && $inicioContrato->lt($cycleStart)) {
                    // Month > 1: 10%
                    if ($isAtrasado) {
                        $comissaoPendente = $precoBase * 0.10;
                        $regra = 'Aluguel recorrente (10%) - Pagamento em atraso pelo inquilino';
                        $categoria = 'pendente';
                        $badgeClass = 'bg-rose-100 text-rose-800';
                    } else {
                        $comissaoOficial = $precoBase * 0.10;
                        $regra = 'Aluguel recorrente (Mês 2+: 10%)';
                        $categoria = 'aluguel_recorrente';
                        $badgeClass = 'bg-teal-100 text-teal-800';
                    }
                } else {
                    $regra = 'Contrato com início futuro';
                    $categoria = 'futuro';
                    $badgeClass = 'bg-gray-100 text-gray-500';
                }
            } elseif ($isEmNegociacao) {
                $comissaoPipeline = $precoBase; // 100% of 1st month expected
                $regra = 'Em negociação (previsão de 100% no 1º mês)';
                $categoria = 'pipeline';
                $badgeClass = 'bg-amber-100 text-amber-800';
            } else {
                $regra = 'Disponível para locação';
                $categoria = 'estoque';
                $badgeClass = 'bg-gray-100 text-gray-600';
            }
        }

        return array_merge($item, [
            'preco_base_calculo' => $precoBase ?? ($item['preco_fechado'] ?? $item['preco_anunciado'] ?? 0.0),
            'comissao_oficial' => round($comissaoOficial, 2),
            'comissao_pipeline' => round($comissaoPipeline, 2),
            'comissao_pendente' => round($comissaoPendente, 2),
            'regra_aplicada' => $regra,
            'categoria' => $categoria,
            'badge_class' => $badgeClass,
        ]);
    }

    /**
     * Discover all relevant months from properties data.
     */
    protected function discoverMonths(array $items): array
    {
        $months = ['2026-11']; // Always ensure target month is available

        foreach ($items as $item) {
            foreach (['data_fechamento', 'inicio_contrato'] as $field) {
                if (!empty($item[$field])) {
                    $dt = Carbon::parse($item[$field]);
                    // If date is > 20th of month, it rolls to next month!
                    $monthBelonging = $dt->day > 20 ? $dt->copy()->addMonth()->format('Y-m') : $dt->format('Y-m');
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
    public function getMonthLabel(string $yearMonth): string
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
