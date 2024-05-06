<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class M_HistoryAcc extends Model
{
    use HasFactory;
    protected $connection = 'extdb';
    protected $table = 'hist_acc';
    protected $fillable = [
        'ID',
        'ACC_NUMBER',
        'VALUE_DATE',
        'ENTRY_TIME',
        'TRANS_DESC',
        'AMOUNT',
        'NO_REF'
    ];
}
