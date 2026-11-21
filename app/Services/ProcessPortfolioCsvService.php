<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessPortfolioCsvService
{
    public function __construct(
        protected NormalizeCsvService $normalizeCsvService,
        protected CalculateCommissionService $calculateCommissionService
    ) {}

    /**
     * Process the portfolio CSV file and persist results for the given report.
     * Uses streaming generator and single-pass aggregation to keep RAM < 25 MB even for 100k+ rows.
     */
    public function process(int $reportId, string $filePath): void
    {
        $report = Report::find($reportId);
        if (!$report) {
            Log::error("Report not found: {$reportId}");
            return;
        }

        $report->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            if (!file_exists($filePath)) {
                throw new RuntimeException("Arquivo CSV não encontrado no caminho: {$filePath}");
            }

            // Fast discovery of target months without loading file into RAM (<0.15s)
            $targetMonths = $this->calculateCommissionService->discoverMonthsFromFile($filePath);

            // Stream parse CSV with Generator (O(1) RAM)
            $stream = $this->normalizeCsvService->parseStream($filePath);

            // Calculate commissions across all months in a single streaming pass
            $results = $this->calculateCommissionService->calculateAll($stream, $targetMonths);

            if ($results['total_items_in_portfolio'] === 0) {
                throw new RuntimeException('O arquivo CSV está vazio ou em formato inválido.');
            }

            // Persist structured JSON in the database
            $report->update([
                'status' => 'completed',
                'data' => $results,
                'error_message' => null,
            ]);

            Log::info("Report {$reportId} successfully processed with {$results['total_items_in_portfolio']} items.");
        } catch (Throwable $e) {
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
