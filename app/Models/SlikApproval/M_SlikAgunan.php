<?php

namespace App\Models\SlikApproval;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SlikAgunan extends Model
{
    use HasFactory;
    protected $table = 'slik_agunan';
    protected $fillable = [
        'ID',
        'SLIK_FASILITAS_ID',
        'jenisAgunanKet',
        'nilaiAgunanMenurutLJK',
        'prosentaseParipasu',
        'tanggalUpdate',
        'nomorAgunan',
        'jenisPengikatan',
        'jenisPengikatanKet',
        'tanggalPengikatan',
        'namaPemilikAgunan',
        'alamatAgunan',
        'kabKotaLokasiAgunan',
        'kabKotaLokasiAgunanKet',
        'tglPenilaianPelapor',
        'peringkatAgunan',
        'kodeLembagaPemeringkat',
        'lembagaPemeringkat',
        'buktiKepemilikan',
        'nilaiAgunanNjop',
        'nilaiAgunanIndep',
        'namaPenilaiIndep',
        'asuransi',
        'tanggalPenilaianPenilaiIndependen',
        'keterangan'
    ];
    
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if ($model->getKey() == null) {
                $model->setAttribute($model->getKeyName(), Str::uuid()->toString());
            }
        });
    }
}
