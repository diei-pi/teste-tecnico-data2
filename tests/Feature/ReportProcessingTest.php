<?php

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioCsvJob;
use App\Models\Report;
use App\Services\CommissionCalculatorService;
use App\Services\CsvNormalizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Duarte Imóveis');
    }

    public function test_uploading_csv_creates_report_and_dispatches_job(): void
    {
        Queue::fake();

        $csvContent = "codigo,tipo,bairro,cidade,negocio,status,preco_anunciado,preco_fechado,data_fechamento,inicio_contrato,observacao\n"
                    . "IMV-001,Apartamento,Pinheiros,São Paulo,Venda,Vendido,\"R$ 980.000,00\",\"R$ 950.000,00\",05/11/2026,,\n";

        $file = UploadedFile::fake()->createWithContent('portfolio.csv', $csvContent);

        $response = $this->post('/reports', [
            'csv_file' => $file,
        ]);

        $this->assertDatabaseHas('reports', [
            'filename' => 'portfolio.csv',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessPortfolioCsvJob::class);
    }

    public function test_job_processes_csv_and_saves_json_data(): void
    {
        $csvPath = base_path('carteira_sabrina_nov2026.csv');

        $report = Report::create([
            'filename' => 'carteira_sabrina_nov2026.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessPortfolioCsvJob($report->id, $csvPath);
        $job->handle(new CsvNormalizerService(), new CommissionCalculatorService());

        $report->refresh();

        $this->assertEquals('completed', $report->status);
        $this->assertNotNull($report->data);
        $this->assertArrayHasKey('results_by_month', $report->data);
        $this->assertArrayHasKey('2026-11', $report->data['results_by_month']);

        $nov = $report->data['results_by_month']['2026-11'];
        $this->assertEquals(87910.00, $nov['totals']['total_garantido']);
    }
}
