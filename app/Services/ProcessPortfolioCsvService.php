<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Log;

class ProcessPortfolioCsvService
{
    public function __construct(
        protected CsvNormalizerService $csvNormalizerService,
        protected CommissionCalculatorService $commissionCalculatorService
    ) {}

    /**
     * Process the portfolio CSV file and persist results for the given report.
     */
    public function process(int $reportId, string $filePath): void
    {
        $report = Report::find($reportId);
        if (!$report) {
            Log::error("Report not found: {$reportId}");
            return;
        }

        try {
            $report->update(['status' => 'processing']);

            // Parse and clean CSV
            $normalizedItems = $this->csvNormalizerService->parse($filePath);

            if (empty($normalizedItems)) {
                throw new \RuntimeException('O arquivo CSV está vazio ou em formato inválido.');
            }

            // Calculate commissions across all months
            $results = $this->commissionCalculatorService->calculateAll($normalizedItems);

            // Persist structured JSON in the database
            $report->update([
                'status' => 'completed',
                'data' => $results,
            ]);

            Log::info("Report {$reportId} successfully processed with {$results['total_items_in_portfolio']} items.");
        } catch (\Throwable $e) {
            Log::error("Error processing report {$reportId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
