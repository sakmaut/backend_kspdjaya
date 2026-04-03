<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class M_LkpProgress extends Model
{
    use HasFactory;
    protected $table = 'v_lkp_progress';
    protected $fillable = [
        'Cabang',
        'NoLKP',
        'Petugas',
        'NamaPetugas',
        'Tanggal',
        'JumlahSurat',
        'Progres',
        'Status',
        'LKP_ID',
        'CABANG_ID'
    ];
}
