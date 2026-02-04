<?php

namespace App\Http\Controllers\Ticketing\TicketingMessages;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketingMessagesEntity extends Model
{
    use HasFactory;

    protected $table = 'tic_ticket_messages';
    protected $fillable = [
        'id',
        'ticket_id',
        'messages',
        'file_path',
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

    public function currentUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
