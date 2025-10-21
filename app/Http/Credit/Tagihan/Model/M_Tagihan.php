<?php

namespace App\Http\Credit\Tagihan\Model;

use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;
use App\Models\M_CrCollateral;
use App\Models\M_Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Tagihan extends Model
{
    use HasFactory;

    protected $table = 'cl_deploy';
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
        'ALAMAT',
        'MCF',
        'ANGSURAN_KE',
        'ANGSURAN',
        'BAYAR',
        'STATUS',
        'CREATED_BY',
        'CREATED_AT',
        'UPDATED_BY',
        'UPDATED_AT',
        'DELETED_BY',
        'DELETED_AT'
    ];

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

    public function customer()
    {
        return $this->hasOne(M_Customer::class, 'CUST_CODE', 'CUST_CODE');
    }

    public function collateral()
    {
        return $this->hasOne(M_CrCollateral::class, 'CR_CREDIT_ID', 'CREDIT_ID');
    }
}
