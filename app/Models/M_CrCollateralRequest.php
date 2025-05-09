<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrCollateralRequest extends Model
{
    use HasFactory;
    protected $table = 'cr_collateral_request';
    protected $fillable = [
        'ID',
        'COLLATERAL_ID',
        'ON_BEHALF',
        'POLICE_NUMBER',
        'ENGINE_NUMBER',
        'CHASIS_NUMBER',
        'BPKB_ADDRESS',
        'BPKB_NUMBER',
        'STNK_NUMBER',
        'INVOICE_NUMBER',
        'STNK_VALID_DATE',
        'DESCRIPTION',
        'STATUS',
        'APPROVED_BY',
        'APPROVED_POSITION',
        'APPROVED_AT',
        'REQUEST_BY',
        'REQUEST_BRANCH',
        'REQUEST_POSITION',
        'REQUEST_AT',
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

    public function cr_collateral()
    {
        return $this->hasOne(M_CrCollateral::class, 'ID', 'COLLATERAL_ID');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'REQUEST_BY');
    }

    public function branch()
    {
        return $this->hasOne(M_Branch::class, 'ID', 'REQUEST_BRANCH');
    }
}
