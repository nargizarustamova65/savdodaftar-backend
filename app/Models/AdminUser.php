<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
    protected $fillable = ['email', 'password', 'name'];
    protected $hidden = ['password'];
}
