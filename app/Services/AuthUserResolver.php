<?php

namespace App\Services;

use App\Contracts\UserResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Готовая реализация для будущего использования с Laravel Auth
 */
class AuthUserResolver implements UserResolver
{
    public function getUserId(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    public function getUserName(): string
    {
        $user = Auth::user();
        return $user ? $user->name : 'Guest';
    }

    public function isAuthenticated(): bool
    {
        return Auth::check();
    }
}