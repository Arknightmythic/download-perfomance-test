<?php

namespace App\Jobs;

use App\Models\ExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateBankDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 2;

    public function __construct(private ExportJob $exportJob) {}

    public function handle(): void
    {
        $this->exportJob->update(['status' => 'processing']);

        try {
            $from     = $this->exportJob->date_from;
            $to       = $this->exportJob->date_to;
            $filename = "exports/bank_data_{$from->format('Ymd')}_{$to->format('Ymd')}_{$this->exportJob->id}.csv";
            $filePath = storage_path("app/$filename");

            if (!is_dir(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            $handle = fopen($filePath, 'w');
            stream_set_write_buffer($handle, 8 * 1024 * 1024); // ✅ 8MB buffer

            fputcsv($handle, [
                'id', 'transaction_date', 'account_number',
                'transaction_type', 'amount', 'balance',
                'description', 'branch_code', 'currency', 'created_at',
            ]);

            // ✅ chunk lebih efisien dari cursor untuk jutaan rows
            DB::table('bank_data')
                ->whereBetween('transaction_date', [
                    $from->format('Y-m-d'),
                    $to->format('Y-m-d'),
                ])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->select([
                    'id', 'transaction_date', 'account_number',
                    'transaction_type', 'amount', 'balance',
                    'description', 'branch_code', 'currency', 'created_at',
                ])
                ->chunk(2000, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, (array) $row);
                    }
                });

            fclose($handle);

            $this->exportJob->update([
                'status'    => 'done',
                'file_path' => $filename,
                'file_size' => filesize($filePath),
            ]);

        } catch (\Throwable $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            $this->exportJob->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}