<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacultyAssignClass extends Model
{
    use HasFactory;
    protected $table = 'faculty_assigned_class';
    protected $guarded = [];
    public $timestamps = false;
}
