<?php

namespace App\Http\Controllers\Saving\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_ProductSaving extends Model
{
    protected $table = 'sv_master_product_saving';
    protected $fillable = [
        'id',
        'product_code',
        'product_name',
        'product_type',
        'interest_rate',
        'min_deposit',
        'admin_fee',
        'term_length',
        'version',
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
}
