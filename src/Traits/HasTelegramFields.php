<?php

namespace ProcessMaker\TelegramPlugin\Traits;

trait HasTelegramFields
{
    public function initializeHasTelegramFields()
    {
        $this->fillable = array_merge($this->fillable, [
            'telegram_chat_id',
            'telegram_auth_token',
            'telegram_verified_at',
            'telegram_username',
            'telegram_first_name',
        ]);

        $this->casts = array_merge($this->casts, [
            'telegram_verified_at' => 'datetime',
        ]);
    }
}
