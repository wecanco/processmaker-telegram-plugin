<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    use HasFactory;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'telegram_chat_id',
        'telegram_auth_token',
        'telegram_verified_at',
        'telegram_username',
        'telegram_first_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'telegram_auth_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'telegram_verified_at' => 'datetime',
    ];

    /**
     * Check if the Telegram account is verified.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return !is_null($this->telegram_verified_at);
    }

    /**
     * Relation to the User model.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }
}
