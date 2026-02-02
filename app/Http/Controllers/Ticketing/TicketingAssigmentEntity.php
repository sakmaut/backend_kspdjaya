<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;
use App\Models\M_CrCollateral;
use App\Models\M_Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketingAssigmentEntity extends Model
{
    use HasFactory;

    protected $table = 'tic_ticket_assigment';
    protected $fillable = [
        'id',
        'ticket_id',
        'user_id',
        'assigned_at',
        'released_at',
        'assigned_by',
        'created_at',
        'updated_at'
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
}
