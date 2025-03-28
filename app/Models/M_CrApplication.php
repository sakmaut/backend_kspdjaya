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
        'CR_SURVEY_ID',
        'BRANCH',
        'FORM_NUMBER',
        'ORDER_NUMBER',
        'CUST_CODE',
        'ENTRY_DATE',
        'SUBMISSION_VALUE',
        'CREDIT_TYPE',
        'INSTALLMENT_COUNT',
        'PERIOD',
        'INSTALLMENT',
        'OPT_PERIODE',
        'FLAT_RATE',
        'EFF_RATE',
        'INTEREST_RATE',
        'TOTAL_INTEREST',
        'INSTALLMENT_TYPE',
        'TENOR',
        'ACC_VALUE',
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

    public function cr_personal()
    {
        return $this->hasOne(M_CrPersonal::class, 'APPLICATION_ID');
    }

    public function credit()
    {
        return $this->hasOne(M_Credit::class, 'ORDER_NUMBER', 'ORDER_NUMBER');
    }

    public static function fpkListData($request, ...$params)
    {
        $getPosition = $request->user()->position;
        $getBranch = $request->user()->branch_id;

        $query = DB::table('cr_application as t1')
            ->join('cr_survey as t2', 't2.id', '=', 't1.CR_SURVEY_ID')
            ->join('branch as t3', 't3.ID', '=', 't1.BRANCH')
            ->join('users as t4', 't4.id', '=', 't2.created_by')
            ->join('application_approval as t6', 't6.cr_application_id', '=', 't1.ID')
            ->join('cr_personal as t7', 't7.APPLICATION_ID', '=', 't1.ID');

        if ($getPosition !== 'HO') {
            $query->where('t1.BRANCH', $getBranch);
        }

        if (!empty($params)) {
            $statuses = [];
            foreach ($params as $param) {
                $statuses[] = $param;
            }
            $query->whereIn('t6.code', $statuses);
        }

        $query->groupBy(
            't1.id',
            't1.ORDER_NUMBER',
            't3.NAME',
            't4.fullname',
            DB::raw('COALESCE(t1.INSTALLMENT_TYPE, t2.jenis_angsuran)'),
            DB::raw('COALESCE(t7.NAME, t2.nama)'),
            DB::raw('COALESCE(t1.SUBMISSION_VALUE, t2.plafond)'),
            DB::raw('COALESCE(t1.tenor, t2.tenor)'),
            't6.application_result',
            't6.code',
            't1.CREATE_DATE'
        )
            ->orderBy('t1.CREATE_DATE', 'desc')
            ->select(
                't1.id',
                't1.ORDER_NUMBER as order_number',
                't3.NAME as cabang',
                't4.fullname as nama_ao',
                DB::raw('COALESCE(t1.INSTALLMENT_TYPE, t2.jenis_angsuran) as jenis_angsuran'),
                DB::raw('COALESCE(t7.NAME, t2.nama) as nama_debitur'),
                DB::raw('COALESCE(t1.SUBMISSION_VALUE, t2.plafond) as plafond'),
                DB::raw('COALESCE(t1.tenor, t2.tenor) as tenor'),
                't6.application_result as status',
                't6.code as status_code'
            );

        return $query->get();
    }
}
