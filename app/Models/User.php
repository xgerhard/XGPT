<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $with = ['settings'];

    protected $fillable = [
        'name',
        'display_name',
        'provider',
        'provider_id',
        'sponsor',
        'token'
    ];

    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }
}
