<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class M_ApplicationApproval extends Model
{
    use HasFactory;
    protected $table = 'application_approval';
    protected $fillable = [
        'id',
        'cr_prospect_id' ,
        'cr_application_id' ,
        'cr_prospect_kapos',
        'cr_prospect_kapos_time',
        'cr_prospect_kapos_note',
        'cr_application_kapos',
        'cr_application_kapos_time',
        'cr_application_kapos_note',
        'cr_application_kapos_desc',
        'cr_application_ho',
        'cr_application_ho_time',
        'cr_application_ho_note',
        'cr_application_ho_desc',
        'application_result'
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
}
