<?php

namespace App\Http\Controllers\Ticketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketingEntity extends Model
{
    use HasFactory;

    protected $table = 'tic_tickets';
    protected $fillable = [
        'id',
        'title',
        'ticket_number',
        'category',
        'priority',
        'status',
        'description',
        'path_image',
        'current_assignee_id',
        'is_closed',
        'created_by',
        'created_at'
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

    public function currentAssignee()
    {
        return $this->belongsTo(User::class, 'current_assignee_id');
    }

    public function assignments()
    {
        return $this->hasMany(TicketingAssigmentEntity::class, 'ticket_id');
    }
}
