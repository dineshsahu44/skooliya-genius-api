<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HwMessageFor extends Model
{
    use HasFactory;
    protected $table = 'hwmessagefor';
    protected $guarded = [];
    public $timestamps = false;
}
