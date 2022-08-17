<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveSession extends Model
{
    use HasFactory;
    protected $table = 'live_session';
    protected $guarded = [];
    public $timestamps = false;
}
