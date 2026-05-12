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

    public int $timeout = 7200; // Tingkatkan ke 2 jam jika data mencapai 6 juta+
    public int $tries = 1;      // Sebaiknya 1 saja, jika gagal jangan diulang otomatis dari awal (file bisa corrupt)

    public function __construct(private ExportJob $exportJob) {}

    public function handle(): void
    {
        $this->exportJob->update(['status' => 'processing']);

        $handle = null; // Inisialisasi awal

        try {
            $from     = $this->exportJob->date_from;
            $to       = $this->exportJob->date_to;
            $filename = "exports/bank_data_{$from->format('Ymd')}_{$to->format('Ymd')}_{$this->exportJob->id}.csv";
            $filePath = storage_path("app/$filename");

            if (!is_dir(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            $handle = fopen($filePath, 'w');
            stream_set_write_buffer($handle, 8 * 1024 * 1024); // 8MB buffer sudah sangat baik

            $filename = "exports/bank_data_{$from->format('Ymd')}_{$to->format('Ymd')}_{$this->exportJob->id}.csv";
            $finalFilePath = storage_path("app/$filename");

            if (!is_dir(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            // TRIK BYPASS WSL2: Gunakan sys_get_temp_dir() yang murni berada di dalam Linux
            $tmpFilePath = sys_get_temp_dir() . '/' . basename($filename);
            
            $handle = fopen($tmpFilePath, 'w');
            stream_set_write_buffer($handle, 8 * 1024 * 1024);

            $headers = [
                'id', 'transaction_date', 'account_number',
                'transaction_type', 'amount', 'balance',
                'description', 'branch_code', 'currency', 'created_at',
            ];
            
            fputcsv($handle, $headers);

            $query = DB::table('bank_data')
                ->whereBetween('transaction_date', [
                    $from->format('Y-m-d'),
                    $to->format('Y-m-d'),
                ])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->select($headers);

            // Proses tulis 6 juta baris sekarang berjalan secepat kilat murni di Linux
            foreach ($query->cursor() as $row) {
                fputcsv($handle, (array) $row);
            }

            fclose($handle);

            // Setelah selesai merangkai 680MB, copy HANYA 1 KALI ke folder Windows
            copy($tmpFilePath, $finalFilePath);
            unlink($tmpFilePath); // Hapus file temporary

            $this->exportJob->update([
                'status'    => 'done',
                'file_path' => $filename,
                'file_size' => filesize($finalFilePath),
            ]);

        } catch (\Throwable $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            // Opsional: Hapus file jika gagal agar tidak memakan space
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
            
            $this->exportJob->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}