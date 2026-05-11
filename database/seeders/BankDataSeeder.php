<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BankDataSeeder extends Seeder
{
    // Konfigurasi
    const TOTAL_ROWS   = 1_000_000; // ganti sesuai kebutuhan test
    const CHUNK_SIZE   = 5_000;     // rows per INSERT query
    const DATE_FROM    = '-6 months';
    const DATE_TO      = 'now';

    private static array $transactionTypes = [
        'CREDIT', 'DEBIT', 'TRANSFER_IN', 'TRANSFER_OUT',
        'PAYMENT', 'WITHDRAWAL', 'DEPOSIT', 'FEE',
    ];

    private static array $branchCodes = [
        'JKT001', 'JKT002', 'BDG001', 'SBY001',
        'MDN001', 'MKS001', 'SMG001', 'PLB001',
    ];

    public function run(): void
    {
        $this->command->info('Seeding bank_data...');
        $this->command->info('Total rows  : ' . number_format(self::TOTAL_ROWS));
        $this->command->info('Chunk size  : ' . number_format(self::CHUNK_SIZE));
        $this->command->newLine();

        // Generate rentang tanggal dari DATE_FROM sampai DATE_TO
        $startTs = strtotime(self::DATE_FROM);
        $endTs   = strtotime(self::DATE_TO);

        $totalChunks = (int) ceil(self::TOTAL_ROWS / self::CHUNK_SIZE);
        $bar         = $this->command->getOutput()->createProgressBar($totalChunks);
        $bar->start();

        $inserted = 0;
        DB::disableQueryLog(); // hemat memory

        while ($inserted < self::TOTAL_ROWS) {
            $remaining  = self::TOTAL_ROWS - $inserted;
            $batchSize  = min(self::CHUNK_SIZE, $remaining);
            $rows       = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $rows[] = $this->makeRow($startTs, $endTs);
            }

            DB::table('bank_data')->insert($rows);

            $inserted += $batchSize;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);
        $this->command->info('Done! Total inserted: ' . number_format($inserted) . ' rows');
    }

    private function makeRow(int $startTs, int $endTs): array
    {
        // Random timestamp dalam rentang, lalu ambil date-nya
        $ts   = random_int($startTs, $endTs);
        $date = date('Y-m-d', $ts);
        $now  = now()->toDateTimeString();

        return [
            'transaction_date' => $date,
            'account_number'   => str_pad((string) random_int(1, 9_999_999_999), 10, '0', STR_PAD_LEFT),
            'transaction_type' => self::$transactionTypes[array_rand(self::$transactionTypes)],
            'amount'           => random_int(10_000, 500_000_000) / 100,
            'balance'          => random_int(0, 1_000_000_000) / 100,
            'description'      => random_int(1, 10) > 3 ? $this->randomDesc() : null,
            'branch_code'      => self::$branchCodes[array_rand(self::$branchCodes)],
            'currency'         => 'IDR',
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function randomDesc(): string
    {
        $descs = [
            'Transfer gaji', 'Pembayaran tagihan', 'Belanja online',
            'Tarik tunai ATM', 'Setor tunai', 'Bayar kartu kredit',
            'Top up e-wallet', 'Cicilan kendaraan', 'Pembayaran BPJS',
            'Bayar PLN', 'Transfer antar rekening', 'Fee administrasi',
        ];

        return $descs[array_rand($descs)];
    }
}