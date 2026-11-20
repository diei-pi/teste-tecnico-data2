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

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $reportId,
        public string $filePath
    ) {}

    public function handle(ProcessPortfolioCsvService $processPortfolioCsvService): void
    {
        $processPortfolioCsvService->process($this->reportId, $this->filePath);
    }
}
