<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoVideo extends Model
{
    use HasFactory;
    protected $table = 'photosvideos';
    protected $guarded = [];
    public $timestamps = false;
}
