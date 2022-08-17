<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MainScreenOption extends Model
{
    use HasFactory;
    protected $table = 'mainscreenoptions';
    protected $guarded = [];
    public $timestamps = false;
}
