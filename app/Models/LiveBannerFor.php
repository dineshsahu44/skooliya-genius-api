<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveBannerFor extends Model
{
    use HasFactory;
    protected $table = 'live_banner_for';
    protected $guarded = [];
    public $timestamps = false;
}
