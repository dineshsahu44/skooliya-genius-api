<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveDoc extends Model
{
    use HasFactory;
    protected $table = 'live_docs';
    protected $guarded = [];
    public $timestamps = false;
}
