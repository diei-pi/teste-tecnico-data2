<?php

namespace Database\Seeders;

use App\Jobs\ProcessPortfolioCsvJob;
use App\Models\Report;
use App\Services\CommissionCalculatorService;
use App\Services\CsvNormalizerService;
use Illuminate\Database\Seeder;

class InitialReportSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = base_path('carteira_sabrina_nov2026.csv');
        if (!file_exists($csvPath)) {
            return;
        }

        $report = Report::create([
            'filename' => 'carteira_sabrina_nov2026.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessPortfolioCsvJob($report->id, $csvPath);
        $job->handle(new CsvNormalizerService(), new CommissionCalculatorService());
    }
}
