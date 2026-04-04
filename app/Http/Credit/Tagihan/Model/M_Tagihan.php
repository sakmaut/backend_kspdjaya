<?php

namespace App\Http\Credit\Tagihan\Model;

use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;
use App\Models\M_Branch;
use App\Models\M_ClSurveyLogs;
use App\Models\M_CrCollateral;
use App\Models\M_Credit;
use App\Models\M_Customer;
use App\Models\M_LkpDetail;
use App\Models\M_Payment;
use App\Models\User;
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
        'CYCLE_AKHIR',
        'N_BOT',
        'TENOR',
        'CATT_SURVEY',
        'ALAMAT',
        'MCF',
        'ANGSURAN_KE',
        'ANGSURAN',
        'AMBC_TOTAL_AWAL',
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

    public function branch()
    {
        return $this->hasOne(M_Branch::class, 'ID', 'BRANCH_ID');
    }

    public function assignUser()
    {
        return $this->belongsTo(User::class, 'USER_ID', 'username');
    }
    
    public function customer()
    {
        return $this->hasOne(M_Customer::class, 'CUST_CODE', 'CUST_CODE');
    }

    public function collateral()
    {
        return $this->hasOne(M_CrCollateral::class, 'CR_CREDIT_ID', 'CREDIT_ID');
    }

    public function credit()
    {
        return $this->hasOne(M_Credit::class, 'LOAN_NUMBER', 'LOAN_NUMBER');
    }

    public function surveyLogs()
    {
        return $this->hasOne(M_ClSurveyLogs::class, 'REFERENCE_ID', 'NO_SURAT')->ofMany('CREATED_AT', 'max');
    }

    public function payments()
    {
        return $this->hasMany(M_Payment::class, 'LOAN_NUM', 'LOAN_NUMBER');
    }
}
