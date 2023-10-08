<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacultyAttendance extends Model
{
    use HasFactory;
    protected $table = 'faculty_attendances';
    protected $guarded = [];
    public $timestamps = false;
}
