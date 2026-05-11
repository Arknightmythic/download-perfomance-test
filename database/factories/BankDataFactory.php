<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BankDataFactory extends Factory
{
    // Data statis untuk performa — hindari faker untuk bulk insert
    private static array $transactionTypes = [
        'CREDIT', 'DEBIT', 'TRANSFER_IN', 'TRANSFER_OUT',
        'PAYMENT', 'WITHDRAWAL', 'DEPOSIT', 'FEE',
    ];

    private static array $branchCodes = [
        'JKT001', 'JKT002', 'BDG001', 'SBY001',
        'MDN001', 'MKS001', 'SMG001', 'PLB001',
    ];

    private static array $currencies = ['IDR', 'USD', 'SGD'];

    public function definition(): array
    {
        $amount  = fake()->randomFloat(2, 10_000, 500_000_000);
        $balance = fake()->randomFloat(2, 0, 1_000_000_000);

        return [
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'account_number'   => fake()->numerify('##########'),
            'transaction_type' => fake()->randomElement(self::$transactionTypes),
            'amount'           => $amount,
            'balance'          => $balance,
            'description'      => fake()->boolean(70) ? fake()->sentence(4) : null,
            'branch_code'      => fake()->randomElement(self::$branchCodes),
            'currency'         => fake()->randomElement(self::$currencies),
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }
}