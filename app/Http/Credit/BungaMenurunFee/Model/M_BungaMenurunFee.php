<?php

namespace App\Http\Credit\BungaMenurunFee\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_BungaMenurunFee extends Model
{
    use HasFactory;

    protected $table = 'bunga_menurun_fee';
    protected $fillable = [
        'ID',
        'LOAN_AMOUNT',
        'INTEREST_PERCENTAGE',
        'INSTALLMENT',
        'ADMIN_FEE',
        'INTEREST_FEE',
        'PROCCESS_FEE',
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
}
