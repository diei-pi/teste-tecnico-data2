<?php

namespace App\Jobs;

use App\Services\ProcessPortfolioCsvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPortfolioCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public int $reportId,
        public string $filePath
    ) {}

    public function handle(ProcessPortfolioCsvService $processPortfolioCsvService): void
    {
        $processPortfolioCsvService->process($this->reportId, $this->filePath);
    }

    /**
     * Handle a job failure (unhandled exceptions, memory exhaustion, timeout, etc.).
     */
    public function failed(?\Throwable $exception): void
    {
        $report = \App\Models\Report::find($this->reportId);
        if ($report && $report->status !== 'completed') {
            $report->update([
                'status' => 'failed',
                'error_message' => 'Ocorreu uma falha no processamento da planilha. Por favor, entre em contato com o suporte técnico.',
            ]);
        }
    }
}
