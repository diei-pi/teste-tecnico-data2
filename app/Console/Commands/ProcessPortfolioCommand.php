<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPortfolioCsvJob;
use App\Models\Report;
use App\Services\ProcessPortfolioCsvService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessPortfolioCommand extends Command
{
    protected $signature = 'portfolio:process {reportId : O ID do relatório a processar}';

    protected $description = 'Executa o processamento do relatório manualmente em primeiro plano (síncrono)';

    public function handle(ProcessPortfolioCsvService $service): int
    {
        $reportId = (int)$this->argument('reportId');
        $report = Report::find($reportId);

        if (!$report) {
            $this->error("Relatório #{$reportId} não encontrado no banco de dados.");
            return 1;
        }

        $this->info("Iniciando processamento manual do Relatório #{$reportId} ({$report->filename})...");

        // Find file path
        $filePath = null;

        // Find file path: check project root first (e.g. carteira_sabrina_nov2026.csv or carteira_100k_linhas.csv)
        if (file_exists(base_path($report->filename))) {
            $filePath = base_path($report->filename);
        } else {
            $disk = Storage::disk('local');
            $files = $disk->allFiles('csv_uploads');
            foreach ($files as $f) {
                $full = $disk->path($f);
                if (file_exists($full)) {
                    $filePath = $full;
                    break;
                }
            }
        }

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado no caminho: {$filePath}");
            return 1;
        }

        $this->line("Arquivo identificado: {$filePath}");
        $this->line("Tamanho: " . round(filesize($filePath) / 1024 / 1024, 2) . " MB");

        $startTime = microtime(true);

        $job = new ProcessPortfolioCsvJob($report->id, $filePath);
        $job->handle($service);

        $report->refresh();
        $duration = round(microtime(true) - $startTime, 2);

        if ($report->status === 'completed') {
            $this->info("✅ Sucesso! Relatório #{$reportId} processado em {$duration} segundos.");
            $nov = $report->data['results_by_month']['2026-11']['totals'] ?? [];
            if (!empty($nov)) {
                $this->table(
                    ['Métrica', 'Valor'],
                    [
                        ['Total Garantido (Caixa)', 'R$ ' . number_format($nov['total_garantido'] ?? 0, 2, ',', '.')],
                        ['Total Pipeline', 'R$ ' . number_format($nov['total_pipeline'] ?? 0, 2, ',', '.')],
                        ['Total Pendente/Atrasado', 'R$ ' . number_format($nov['total_pendente'] ?? 0, 2, ',', '.')],
                        ['Total Imóveis', number_format($nov['imoveis_count'] ?? 0, 0, ',', '.')],
                    ]
                );
            }
            return 0;
        }

        $this->error("❌ Falha no processamento: " . ($report->error_message ?? 'Erro desconhecido'));
        return 1;
    }
}
