<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class M_CrApplication extends Model
{
    use HasFactory;
    protected $table = 'cr_application';
    protected $fillable = [
        'ID',
        'CR_PROSPECT_ID',
        'BRANCH',
        'FORM_NUMBER',
        'ORDER_NUMBER',
        'CUST_CODE',
        'ENTRY_DATE',
        'SUBMISSION_VALUE',
        'CREDIT_TYPE',
        'INSTALLMENT_COUNT',
        'PERIOD',
        'PLAFOND',
        'TENOR',
        'INSTALLMENT',
        'OPT_PERIODE',
        'FLAT_RATE',
        'EFF_RATE',
        'POKOK_PEMBAYARAN',
        'NET_ADMIN',
        'TOTAL_ADMIN',
        'CADANGAN',
        'PAYMENT_WAY',
        'PROVISION',
        'INSURANCE',
        'TRANSFER_FEE',
        'INTEREST_MARGIN',
        'PRINCIPAL_MARGIN',
        'LAST_INSTALLMENT',
        'INTEREST_MARGIN_EFF_ACTUAL',
        'INTEREST_MARGIN_EFF_FLAT',
        'VERSION',
        'CREATE_DATE',
        'CREATE_BY',
        'MOD_DATE',
        'MOD_BY',
        'DELETED_AT',
        'DELETED_BY'
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

    public static function fpkListData($param = null){

        $query =  DB::table('cr_application as t1')
                    ->select(
                        't1.id',
                        't3.NAME as cabang',
                        't5.NAMA as nama_ao',
                        't2.nama as nama_debitur',
                        't2.plafond',
                        't2.tenor',
                        't6.application_result as status'
                    )
                    ->join('cr_prospect as t2', 't2.id', '=', 't1.CR_PROSPECT_ID')
                    ->join('branch as t3', 't3.ID', '=', 't1.BRANCH')
                    ->join('users as t4', 't4.id', '=', 't2.ao_id')
                    ->join('hr_employee as t5', 't5.ID', '=', 't4.employee_id')
                    ->join('application_approval as t6', 't6.cr_application_id', '=', 't1.ID');

        if ($param !== null) {
            $query->where('t6.application_result','!=', $param);
        }
    
        $results = $query->get();
        return $results;
    }
}
