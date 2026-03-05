<?php

namespace App\Http\Controllers\Payment\ResetPrintKwitansi\ResetPrintKwitansiLog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResetPrintKwitansiLogModel extends Model
{
    use HasFactory;

    protected $table = 'log_print_update';

    protected $fillable = [
        'log_print_id',
        'description',
        'created_by',
        'created_at'
    ];

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';  
    public $timestamps = false;
}
