<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankData extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_date',
        'account_number',
        'transaction_type',
        'amount',
        'balance',
        'description',
        'branch_code',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount'           => 'decimal:2',
            'balance'          => 'decimal:2',
        ];
    }
}