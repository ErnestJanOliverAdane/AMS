<?php

namespace App\Models;

use App\Casts\Encrypted;
use Illuminate\Database\Eloquent\Model;

/** A message sent from the public "Get Started" form by someone without an account. */
class GuestInquiry extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'services', 'message', 'status', 'handled_at'];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'phone' => Encrypted::class,
            'email' => Encrypted::class,
            'message' => Encrypted::class,
        ];
    }
}
