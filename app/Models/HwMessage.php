<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HwMessage extends Model
{
    use HasFactory;
    protected $table = 'hwmessage';
    protected $guarded = [];
    public $timestamps = false;
}
