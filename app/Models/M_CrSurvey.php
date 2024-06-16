<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class M_CrSurvey extends Model
{
    use HasFactory;
    protected $table = 'cr_survey';
    protected $fillable = [
        'id', 
        'branch_id', 
        'visit_date', 
        'tujuan_kredit', 
        'plafond', 
        'tenor', 
        'category', 
        'nama', 
        'tgl_lahir', 
        'ktp', 
        'hp', 
        'alamat', 
        'rt', 
        'rw', 
        'province', 
        'city', 
        'kelurahan', 
        'kecamatan', 
        'zip_code', 
        'work_period', 
        'income_personal', 
        'income_spouse', 
        'income_other', 
        'usaha', 
        'sector',
        'expenses', 
        'survey_note', 
        'created_by', 
        'created_at', 
        'updated_by', 
        'updated_at',
        'deleted_by', 
        'deleted_at'
    ];
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';
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

    public static function show_admin($branchId){

        $query = self::select('*')
                        ->leftJoin('survey_approval', 'survey_approval.CR_SURVEY_ID', '=', 'cr_survey.id')
                        ->where('cr_survey.branch_id', $branchId)
                        // ->where(function ($query) {
                        //     $values = ['1:approve', '2:created_fpk', '3:waiting kapos', '4:waiting ho'];
                        //     foreach ($values as $value) {
                        //         $query->orWhere('survey_approval.APPROVAL_RESULT', 'like', "%{$value}%");
                        //     }
                        // })
                        ->whereNull('deleted_at')
                        ->get();

        return $query;
    }

    public static function prospek_jaminan()
    {
        $results = DB::table('cr_prospect as t0')
                    ->select(
                        't0.*',
                        't1.id as id_prospek_jaminan',
                        't1.cr_prospect_id as cr_prospect_id_jaminan',
                        't1.type',
                        't1.collateral_value',
                        't1.description',
                        't2.id as id_prospek_person',
                        't2.cr_prospect_id as cr_prospect_id_person',
                        't2.ktp as ktp_jaminan',
                        't2.nama as nama_jaminan',
                        't2.tgl_lahir as tgl_lahir_jaminan',
                        't2.pekerjaan as pekerjaan_jaminan',
                        't2.status as status_jaminan'
                    )
                    ->leftJoin('cr_prospect_col as t1', 't1.cr_prospect_id', '=', 't0.id')
                    ->leftJoin('cr_prospect_person as t2', 't2.cr_prospect_id', '=', 't0.id')
                    ->get();

        return $results;
    }
}
