<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'status',
        'error_message',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
