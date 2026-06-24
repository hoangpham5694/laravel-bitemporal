<?php

namespace HoangPhamDev\Bitemporal\Tests\Models;

use HoangPhamDev\Bitemporal\Traits\HasBitemporal;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasBitemporal;

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'valid_from',
        'valid_to',
        'transaction_from',
        'transaction_to',
    ];

    protected $casts = [
        'operated_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'transaction_from' => 'datetime',
        'transaction_to' => 'datetime',
    ];
}
