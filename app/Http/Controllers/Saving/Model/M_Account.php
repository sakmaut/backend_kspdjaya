<?php

namespace App\Http\Controllers\Saving\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Account extends Model
{
    protected $table = 'sv_master_account';
    protected $fillable = [
        'id',
        'stat_rec',
        'acc_number',
        'acc_name',
        'cust_code',
        'branch',
        'acc_type',
        'clear_bal',
        'min_bal',
        'date_last_trans',
        'date_acc_open',
        'date_acc_close',
        'block_bal',
        'plafond_amount',
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
