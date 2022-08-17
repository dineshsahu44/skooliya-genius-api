<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BirthdayCard extends Model
{
    use HasFactory;
    protected $table = 'birthdaycard';
    protected $guarded = [];
    public $timestamps = false;
}
