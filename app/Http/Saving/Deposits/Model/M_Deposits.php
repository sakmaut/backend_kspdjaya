<?php

namespace App\Http\Saving\Deposits\Model;

use App\Http\Controllers\Saving\Model\M_Account;
use App\Models\M_Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Deposits extends Model
{
    use HasFactory;

    protected $table = 'deposits';

    protected $fillable = [
        'id',
        'status',
        'deposit_number',
        'deposit_holder',
        'deposit_code',
        'branch',
        'cust_code',
        'period',
        'term',
        'roll_over',
        'int_rate',
        'entry_date',
        'ro_date',
        'mature_date',
        'pcpl_due_date',
        'int_due_date',
        'calc_day',
        'deposit_value',
        'flag_tax',
        'accu_tax_val',
        'accu_int_val',
        'acc_source',
        'acc_source_num',
        'acc_source_name',
        'acc_destination',
        'acc_destination_num',
        'acc_destination_name',
        'descr',
        'print_count',
        'print_by',
        'print_at',
        'created_by',
        'created_at',
    ];

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

    public function customer()
    {
        return $this->hasOne(M_Customer::class, 'CUST_CODE', 'cust_code');
    }

    public function account_source()
    {
        return $this->hasOne(M_Account::class, 'acc_number', 'acc_source');
    }

    public function account_destination()
    {
        return $this->hasOne(M_Account::class, 'acc_number', 'acc_destination');
    }
}
