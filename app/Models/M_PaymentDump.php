<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class M_PaymentDump extends Model
{
    use HasFactory;
    protected $connection = 'extdb';
    protected $table = 'payment_dump';
    protected $fillable = [
        'LOAN_NUM',
        'VALUE_DATE',
        'TITLE',
        'ORIGINAL_AMOUNT/100',
        'OS_PCPL_AMOUNT/100',
        'REF_NUM',
        'ACC_KEYS'
    ];
}
