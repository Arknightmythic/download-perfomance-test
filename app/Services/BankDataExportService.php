<?php

namespace App\Services;

use App\Models\BankData;
use Illuminate\Support\Carbon;

class BankDataExportService
{
    const STREAM_THRESHOLD = 1_000; // ✅ hampir semua masuk queue

    public function estimateRowCount(Carbon $from, Carbon $to): int
{
    // ✅ Pakai reltuples — approximate count, jauh lebih cepat
    $result = \DB::selectOne("
        SELECT reltuples::bigint AS estimate
        FROM pg_class
        WHERE relname = 'bank_data'
    ");

    // Kalau rentang < 30 hari, proporsi dari total
    $totalDays = 180; // sesuaikan dengan rentang data seed kamu
    $rangeDays = $from->diffInDays($to) + 1;

    return (int) (($result->estimate ?? 0) * ($rangeDays / $totalDays));
}

    public function streamCsv(Carbon $from, Carbon $to, $outputStream): void
    {
        $headers = [
            'id', 'transaction_date', 'account_number',
            'transaction_type', 'amount', 'balance',
            'description', 'branch_code', 'currency', 'created_at',
        ];

        fputcsv($outputStream, $headers);

        BankData::whereBetween('transaction_date', [
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ])
        ->orderBy('transaction_date')
        ->orderBy('id')
        ->select($headers)
        ->cursor()
        ->each(fn($row) => fputcsv($outputStream, $row->toArray()));
    }

    public function generateToFile(Carbon $from, Carbon $to, string $filePath): void
    {
        $handle = fopen($filePath, 'w');
        stream_set_write_buffer($handle, 8 * 1024 * 1024); // ✅ 8MB buffer
        $this->streamCsv($from, $to, $handle);
        fclose($handle);
    }
}