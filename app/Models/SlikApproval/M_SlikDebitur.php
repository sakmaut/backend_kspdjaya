<?php

namespace App\Models\SlikApproval;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SlikDebitur extends Model
{
    use HasFactory;
    protected $table = 'slik_debitur';
    protected $fillable = [
       'ID',
       'SLIK_APPROVAL_ID',
       'SLIK_NUMBER',
       'namaDebitur',
       'identitas',
       'noIdentitas',
       'alamat',
       'jenisKelamin',
       'jenisKelaminKet',
       'npwp',
       'tempatLahir',
       'tanggalLahir',
       'pelapor',
       'pelaporKet',
       'tanggalDibentuk',
       'tanggalUpdate',
       'kelurahan',
       'kecamatan',
       'kabKota',
       'kabKotaKet',
       'kodePos',
       'negara',
       'negaraKet',
       'pekerjaan',
       'pekerjaanKet',
       'tempatBekerja',
       'bidangUsaha',
       'bidangUsahaKet',
       'kodeGelarDebitur',
       'statusGelarDebitur'
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
