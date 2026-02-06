<?php

namespace App\Models\TableViews;

use App\Models\M_CrCollateralDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ColllectorVisits extends Model
{
    use HasFactory;
    protected $table = 'vw_tagihan_collector';
    protected $fillable = [
       'ID',
       'NO_SURAT',
       'USER_ID',
       'BRANCH_ID',
       'CREDIT_ID',
       'LOAN_NUMBER',
       'CUST_CODE',
       'TGL_JTH_TEMPO',
       'CYCLE_AWAL',
       'N_BOT',
       'MCF',
       'ANGSURAN_KE',
       'ANGSURAN',
       'LKP_NUMBER',
       'total_bayar',
       'DESCRIPTION',
       'CONFIRM_DATE',
       'NAME',
       'INS_ADDRESS',
       'INS_KECAMATAN',
       'INS_KELURAHAN',
       'PHONE_HOUSE',
       'PHONE_PERSONAL',
       'total_denda',
       'unit',
       'COLLATERAL_ID',
       'POLICE_NUMBER',
       'PRODUCTION_YEAR',
       'nama_cabang',
       'fullname'
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

    public function collateralDocuments()
    {
        return $this->hasMany(M_CrCollateralDocument::class, 'COLLATERAL_ID', 'COLLATERAL_ID');
    }
}
