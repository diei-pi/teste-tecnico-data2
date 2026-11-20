<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPortfolioCsvJob;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Dashboard view: shows latest or selected report and historical list.
     */
    public function index(Request $request): View
    {
        $reports = Report::orderBy('created_at', 'desc')->get();

        $selectedReportId = $request->query('report_id');
        $currentReport = null;

        if ($selectedReportId) {
            $currentReport = $reports->firstWhere('id', (int)$selectedReportId);
        }

        if (!$currentReport) {
            // Default to latest completed report, or latest report in general
            $currentReport = $reports->firstWhere('status', 'completed') ?? $reports->first();
        }

        $selectedMonth = $request->query('month');
        $monthData = null;
        $availableMonths = [];

        if ($currentReport && $currentReport->status === 'completed' && !empty($currentReport->data)) {
            $availableMonths = $currentReport->data['available_months'] ?? [];
            if (!$selectedMonth || !in_array($selectedMonth, $availableMonths)) {
                $selectedMonth = $currentReport->data['default_month'] ?? ($availableMonths[0] ?? null);
            }

            if ($selectedMonth && isset($currentReport->data['results_by_month'][$selectedMonth])) {
                $monthData = $currentReport->data['results_by_month'][$selectedMonth];
            }
        }

        return view('reports.dashboard', [
            'reports' => $reports,
            'currentReport' => $currentReport,
            'selectedMonth' => $selectedMonth,
            'availableMonths' => $availableMonths,
            'monthData' => $monthData,
        ]);
    }

    /**
     * Store uploaded CSV and dispatch async background processing job.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,text/plain|max:20480', // up to 20MB
        ]);

        $file = $request->file('csv_file');
        $originalName = $file->getClientOriginalName();
        $storedPath = $file->storeAs('csv_uploads', uniqid('portfolio_') . '.csv', 'local');
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($storedPath);

        $report = Report::create([
            'filename' => $originalName,
            'status' => 'pending',
        ]);

        // Dispatch background processing job to database queue
        ProcessPortfolioCsvJob::dispatch($report->id, $fullPath);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'report_id' => $report->id,
                'status' => $report->status,
                'message' => 'Upload recebido! Processando em segundo plano.',
            ]);
        }

        return redirect()->route('dashboard', ['report_id' => $report->id]);
    }

    /**
     * Status endpoint for frontend polling.
     */
    public function status(Report $report): JsonResponse
    {
        return response()->json([
            'id' => $report->id,
            'status' => $report->status,
            'error_message' => $report->error_message,
            'has_data' => !empty($report->data),
        ]);
    }
}
