<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_HrEmployeeDocument extends Model
{
    use HasFactory;
    protected $table = 'hr_employee_document';
    protected $fillable = [
        'ID',
        'USERS_ID',
        'TYPE',
        'PATH',
        'CREATED_AT'
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

    static function attachment($employeeId,$type){
        $query = self::where(['USERS_ID' => $employeeId,'TYPE' => $type])
                    ->orderBy('CREATED_AT', 'desc')
                    ->first();

        return $query;
    }
}
