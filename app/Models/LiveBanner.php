<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveBanner extends Model
{
    use HasFactory;
    protected $table = 'live_banner';
    protected $guarded = [];
    public $timestamps = false;
}
