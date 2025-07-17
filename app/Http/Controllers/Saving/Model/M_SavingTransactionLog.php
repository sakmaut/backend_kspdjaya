<?php

namespace App\Http\Controllers\Saving\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SavingTransactionLog extends Model
{
    protected $table = 'saving_transactions_log';
    protected $fillable = [
        'id',
        'acc_number',
        'transaction_type',
        'amount',
        'description',
        'created_by',
        'created_at'
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
