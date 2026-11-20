<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Services\ProcessPortfolioCsvService;
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

        app(ProcessPortfolioCsvService::class)->process($report->id, $csvPath);
    }
}
