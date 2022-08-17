<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveClassFor extends Model
{
    use HasFactory;
    protected $table = 'live_classes_for';
    protected $guarded = [];
    public $timestamps = false;
}
