<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveSessionFor extends Model
{
    use HasFactory;
    protected $table = 'live_session_for';
    protected $guarded = [];
    public $timestamps = false;
}
