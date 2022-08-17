<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveClass extends Model
{
    use HasFactory;
    protected $table = 'live_classes';
    protected $guarded = [];
    public $timestamps = false;
}
