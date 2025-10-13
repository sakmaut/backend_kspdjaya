<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Lkp extends Model
{
    use HasFactory;
    protected $table = 'cl_lkp';
    protected $fillable = [
        'ID',
        'USER_ID',
        'LKP_NUMBER',
        'BRANCH_ID',
        'NOA',
        'TOTAL_ANGSURAN',
        'STATUS',
        'STATUS_EXP',
        'CREATED_BY',
        'CREATED_AT',
        'UPDATED_BY',
        'UPDATED_AT',
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

    public function detail()
    {
        return $this->hasMany(M_LkpDetail::class, 'LKP_ID');
    }
}
