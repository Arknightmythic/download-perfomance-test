<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    protected $fillable = [
        'user_id',
        'date_from',
        'date_to',
        'status',
        'file_path',
        'file_size',
        'estimated_rows',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to'   => 'date',
        ];
    }
}