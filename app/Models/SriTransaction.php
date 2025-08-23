<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SriTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'type',
        'request_data',
        'response_data',
        'success',
        'error_message',
    ];

    protected $casts = [
        'request_data' => 'json',
        'response_data' => 'json',
        'success' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
