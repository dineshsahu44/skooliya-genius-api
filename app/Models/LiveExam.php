<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveExam extends Model
{
    use HasFactory;
    protected $table = 'live_exam';
    protected $guarded = [];
    public $timestamps = false;
}
