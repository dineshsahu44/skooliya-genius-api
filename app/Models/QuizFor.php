<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizFor extends Model
{
    use HasFactory;
    protected $table = 'quizfor';
    protected $guarded = [];
    public $timestamps = false;
}
