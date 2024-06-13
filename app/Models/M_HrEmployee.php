<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class M_HrEmployee extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'hr_employee';
    protected $fillable = [
       'ID',
       'NIK',
       'NAMA',
       'AO_CODE',
       'BRANCH_ID',
       'JABATAN',
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
       'ADDRESS_KTP',
       'RT_KTP',
       'RW_KTP',
       'PROVINCE_KTP',
       'CITY_KTP',
       'KELURAHAN_KTP',
       'KECAMATAN_KTP',
       'ZIP_CODE_KTP',
       'ADDRESS',
       'RT',
       'RW',
       'PROVINCE',
       'CITY',
       'KELURAHAN',
       'KECAMATAN',
       'ZIP_CODE',
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
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if ($model->getKey() == null) {
                $model->setAttribute($model->getKeyName(), Str::uuid()->toString());
            }
        });
    }

    public static function findEmployee($employeeID){
        
        $query =self::where('ID', $employeeID)
                    ->whereNull('DELETED_BY')
                    ->orWhereNull('DELETED_AT')
                    ->first();

        return $query;
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class,'employee_id');
    }
}
