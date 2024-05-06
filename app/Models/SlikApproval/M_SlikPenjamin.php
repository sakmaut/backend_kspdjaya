<?php

namespace App\Models\SlikApproval;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SlikPenjamin extends Model
{
    use HasFactory;
    protected $table = 'slik_penjamin';
    protected $fillable = [
        'ID',
        'SLIK_FASILITAS_ID',
        'namaPenjamin',
        'nomorIdentitas',
        'tanggalUpdate',
        'tanggalBuat',
        'kodeJenisPenjamin',
        'keteranganJenisPenjamin',
        'alamatPenjamin',
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
