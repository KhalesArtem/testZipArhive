<?php

namespace App\Services;

use App\Contracts\UserResolver;

class HardcodedUserResolver implements UserResolver
{
    private const DEFAULT_USER_ID = 1;
    private const DEFAULT_USER_NAME = 'Demo User';

    public function getUserId(): int
    {
        // В будущем здесь можно использовать:
        // return Auth::id() ?? self::DEFAULT_USER_ID;
        // return session('user_id', self::DEFAULT_USER_ID);
        return self::DEFAULT_USER_ID;
    }

    public function getUserName(): string
    {
        // В будущем здесь можно использовать:
        // return Auth::user()?->name ?? self::DEFAULT_USER_NAME;
        return self::DEFAULT_USER_NAME;
    }

    public function isAuthenticated(): bool
    {
        // В будущем здесь можно использовать:
        // return Auth::check();
        return true; // Для демо всегда авторизован
    }
}