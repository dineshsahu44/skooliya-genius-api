<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveExamFor extends Model
{
    use HasFactory;
    protected $table = 'live_exam_for';
    protected $guarded = [];
    public $timestamps = false;
}
