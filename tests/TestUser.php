<?php

namespace Studio\Totem\Tests;

use Database\Factories\TotemUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TestUser extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): TotemUserFactory
    {
        return TotemUserFactory::new();
    }
}
