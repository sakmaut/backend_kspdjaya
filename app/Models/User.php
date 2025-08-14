<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'employee_id',
        'email',
        'status',
        'password',
        'fullname',
        'branch_id',
        'position',
        'no_ktp',
        'alamat',
        'gender',
        'status',
        'keterangan',
        'mobile_number',
        'profile_photo_path',
        'created_by',
        'updated_by',
        'updated_at',
        'deleted_by',
        'deleted_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function branch()
    {
        return $this->hasOne(M_Branch::class, 'ID', 'branch_id');
    }

    public function accessMenus()
    {
        return $this->hasMany(M_MasterUserAccessMenu::class, 'users_id', 'id');
    }
}
