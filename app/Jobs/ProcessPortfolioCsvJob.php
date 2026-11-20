<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\CommissionCalculatorService;
use App\Services\CsvNormalizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPortfolioCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $reportId,
        public string $filePath
    ) {}

    public function handle(
        CsvNormalizerService $normalizer,
        CommissionCalculatorService $calculator
    ): void {
        $report = Report::find($this->reportId);
        if (!$report) {
            Log::error("Report not found: {$this->reportId}");
            return;
        }

        try {
            $report->update(['status' => 'processing']);

            // Parse and clean CSV
            $normalizedItems = $normalizer->parse($this->filePath);

            if (empty($normalizedItems)) {
                throw new \RuntimeException('O arquivo CSV está vazio ou em formato inválido.');
            }

            // Calculate commissions across all months
            $results = $calculator->calculateAll($normalizedItems);

            // Persist structured JSON in the database
            $report->update([
                'status' => 'completed',
                'data' => $results,
            ]);

            Log::info("Report {$this->reportId} successfully processed with {$results['total_items_in_portfolio']} items.");
        } catch (\Throwable $e) {
            Log::error("Error processing report {$this->reportId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
