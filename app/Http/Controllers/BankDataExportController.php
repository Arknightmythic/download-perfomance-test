<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateBankDataExport;
use App\Models\ExportJob;
use App\Services\BankDataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankDataExportController extends Controller
{
    public function __construct(private BankDataExportService $exportService) {}

    public function export(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date|before_or_equal:date_to',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $from = Carbon::parse($request->date_from)->startOfDay();
        $to   = Carbon::parse($request->date_to)->endOfDay();

        // Batasi maksimal 90 hari untuk mencegah abuse
        if ($from->diffInDays($to) > 90) {
            return response()->json(['message' => 'Maksimal rentang 90 hari'], 422);
        }

        $estimatedRows = $this->exportService->estimateRowCount($from, $to);

        // Kalau kecil → stream langsung
        if ($estimatedRows <= BankDataExportService::STREAM_THRESHOLD) {
            return $this->streamResponse($from, $to);
        }

        // Kalau besar → dispatch ke queue
        return $this->queueExport($from, $to, $estimatedRows);
    }

    private function streamResponse(Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = "bank_data_{$from->format('Ymd')}_{$to->format('Ymd')}.csv";

        return response()->stream(function () use ($from, $to) {
            $this->exportService->streamCsv($from, $to, fopen('php://output', 'w'));
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'X-Accel-Buffering'   => 'no', // penting untuk Nginx streaming
        ]);
    }

    private function queueExport(Carbon $from, Carbon $to, int $estimatedRows)
    {
        $exportJob = ExportJob::create([
            'user_id'       => auth()->id(),
            'date_from'     => $from,
            'date_to'       => $to,
            'status'        => 'pending',
            'estimated_rows'=> $estimatedRows,
        ]);

        GenerateBankDataExport::dispatch($exportJob);

        return response()->json([
            'message'        => 'Export sedang diproses, Anda akan mendapat notifikasi.',
            'export_job_id'  => $exportJob->id,
            'estimated_rows' => $estimatedRows,
        ], 202);
    }

    // Cek status & download hasil queue
    public function status(ExportJob $exportJob)
    {
        return response()->json([
            'status'     => $exportJob->status,
            'download_url'=> $exportJob->status === 'done'
                ? route('export.download', $exportJob)
                : null,
        ]);
    }

    public function download(ExportJob $exportJob)
    {
        abort_unless($exportJob->status === 'done', 404);

        return response()->download(storage_path("app/{$exportJob->file_path}"));
    }
}