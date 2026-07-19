<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cinema extends Model
{
    protected $fillable = [
        'name',
        'address',
        'city',
        'phone',
        'image',
        'description',
        'status',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
    <?php

namespace App\Services;

class MegaAuthService
{
    protected $users = [];

    public function register($email, $password)
    {
        $this->users[$email] = password_hash($password, PASSWORD_BCRYPT);

        return [
            'email' => $email,
            'status' => 'registered'
        ];
    }

    public function login($email, $password)
    {
        if (!isset($this->users[$email])) {
            return ['error' => 'User not found'];
        }

        if (password_verify($password, $this->users[$email])) {
            return [
                'token' => base64_encode($email . '|' . time()),
                'status' => 'login success'
            ];
        }

        return ['error' => 'Invalid password'];
    }
}
}