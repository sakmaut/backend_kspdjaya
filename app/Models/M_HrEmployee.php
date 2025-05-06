<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_HrEmployee extends Model
{
    use HasFactory;
    protected $table = 'hr_employee';
    protected $fillable = [
        'ID',
        'NIK',
        'NAMA',
        'AO_CODE',
        'BLOOD_TYPE',
        'GENDER',
        'PENDIDIKAN',
        'UNIVERSITAS',
        'JURUSAN',
        'IPK',
        'IBU_KANDUNG',
        'STATUS_KARYAWAN',
        'NAMA_PASANGAN',
        'TANGGUNGAN',
        'NO_KTP',
        'NAMA_KTP',
        'ALAMAT_KTP',
        'SECTOR_KTP',
        'DISTRICT_KTP',
        'SUB_DISTRICT_KTP',
        'ALAMAT_TINGGAL',
        'SECTOR_TINGGAL',
        'DISTRICT_TINGGAL',
        'SUB_DISTRICT_TINGGAL',
        'TGL_LAHIR',
        'TEMPAT_LAHIR',
        'AGAMA',
        'TELP',
        'HP',
        'NO_REK_CF',
        'NO_REK_TF',
        'EMAIL',
        'NPWP',
        'SUMBER_LOKER',
        'KET_LOKER',
        'INTERVIEW',
        'TGL_KELUAR',
        'ALASAN_KELUAR',
        'CUTI',
        'PHOTO_LOC',
        'SPV',
        'STATUS_MST',
        'CREATED_BY',
        'CREATED_AT',
        'UPDATED_BY',
        'UPDATED_AT',
        'DELETED_BY',
        'DELETED_AT'
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

    public function hr_rolling()
    {
        return $this->hasOne(M_HrRolling::class, 'NIK', 'NIK');
    }
}
