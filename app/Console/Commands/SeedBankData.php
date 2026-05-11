<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedBankData extends Command
{
    protected $signature = 'bankdata:seed
                            {--rows=100000   : Jumlah rows yang di-insert}
                            {--chunk=5000    : Jumlah rows per bulk insert}
                            {--from=-6months : Tanggal mulai (strtotime format)}
                            {--to=now        : Tanggal akhir (strtotime format)}
                            {--truncate      : Hapus data lama sebelum seed}';

    protected $description = 'Seed dummy bank transaction data untuk performance testing';

    private static array $transactionTypes = [
        'CREDIT', 'DEBIT', 'TRANSFER_IN', 'TRANSFER_OUT',
        'PAYMENT', 'WITHDRAWAL', 'DEPOSIT', 'FEE',
    ];

    private static array $branchCodes = [
        'JKT001', 'JKT002', 'BDG001', 'SBY001',
        'MDN001', 'MKS001', 'SMG001', 'PLB001',
    ];

    public function handle(): int
    {
        $totalRows = (int) $this->option('rows');
        $chunkSize = (int) $this->option('chunk');
        $startTs   = strtotime($this->option('from'));
        $endTs     = strtotime($this->option('to'));

        if ($startTs === false || $endTs === false) {
            $this->error('Format tanggal tidak valid!');
            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            $this->warn('Truncating bank_data table...');
            DB::table('bank_data')->truncate();
        }

        $this->info("Seeding {$totalRows} rows ke bank_data...");
        $this->info("Rentang: " . date('Y-m-d', $startTs) . " → " . date('Y-m-d', $endTs));
        $this->newLine();

        $totalChunks = (int) ceil($totalRows / $chunkSize);
        $bar         = $this->output->createProgressBar($totalChunks);
        $bar->setFormat(' %current%/%max% chunks [%bar%] %percent:3s%% | Elapsed: %elapsed% | ETA: %estimated%');
        $bar->start();

        $inserted = 0;
        DB::disableQueryLog();

        while ($inserted < $totalRows) {
            $batchSize = min($chunkSize, $totalRows - $inserted);
            $rows      = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $rows[] = $this->makeRow($startTs, $endTs);
            }

            DB::table('bank_data')->insert($rows);

            $inserted += $batchSize;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('✓ Selesai! ' . number_format($inserted) . ' rows berhasil di-insert.');

        // Tampilkan distribusi per tanggal (sample)
        $this->newLine();
        $this->info('Sample distribusi per bulan:');
        $dist = DB::table('bank_data')
            ->selectRaw("TO_CHAR(transaction_date, 'YYYY-MM') as month, COUNT(*) as total")
            ->groupByRaw("TO_CHAR(transaction_date, 'YYYY-MM')")
            ->orderBy('month')
            ->get();

        $this->table(['Bulan', 'Total Rows'], $dist->map(fn($r) => [
            $r->month,
            number_format($r->total),
        ]));

        return self::SUCCESS;
    }

    private function makeRow(int $startTs, int $endTs): array
    {
        $now = now()->toDateTimeString();

        return [
            'transaction_date' => date('Y-m-d', random_int($startTs, $endTs)),
            'account_number'   => str_pad((string) random_int(1, 9_999_999_999), 10, '0', STR_PAD_LEFT),
            'transaction_type' => self::$transactionTypes[array_rand(self::$transactionTypes)],
            'amount'           => random_int(10_000, 50_000_000_000) / 100,
            'balance'          => random_int(0, 100_000_000_000) / 100,
            'description'      => random_int(1, 10) > 3 ? $this->randomDesc() : null,
            'branch_code'      => self::$branchCodes[array_rand(self::$branchCodes)],
            'currency'         => 'IDR',
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function randomDesc(): string
    {
        static $descs = [
            'Transfer gaji', 'Pembayaran tagihan', 'Belanja online',
            'Tarik tunai ATM', 'Setor tunai', 'Bayar kartu kredit',
            'Top up e-wallet', 'Cicilan kendaraan', 'Pembayaran BPJS',
            'Bayar PLN', 'Transfer antar rekening', 'Fee administrasi',
        ];

        return $descs[array_rand($descs)];
    }
}