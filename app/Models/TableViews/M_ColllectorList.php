<?php

namespace App\Models\TableViews;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ColllectorList extends Model
{
    use HasFactory;
    protected $table = 'vw_tagihan_list';
    protected $fillable = [
        'ID',
        'NO_SURAT',
        'NAME',
        'LKP_NUMBER',
        'LOAN_NUMBER',
        'TGL_JTH_TEMPO',
        'USER_ID',
        'BRANCH_ID',
        'ANGSURAN_KE',
        'ANGSURAN',
        'INS_ADDRESS',
        'DESCRIPTION',
        'SURVEY_DATE',
        'CONFIRM_DATE',
        'PATH'
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
