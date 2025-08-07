<?php

namespace App\Providers;

use App\Contracts\UserResolver;
use App\Services\HardcodedUserResolver;
use Illuminate\Support\ServiceProvider;

class UserResolverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Для переключения на реальную аутентификацию достаточно изменить эту строку:
        // $this->app->singleton(UserResolver::class, AuthUserResolver::class);
        
        $this->app->singleton(UserResolver::class, HardcodedUserResolver::class);
    }

    public function boot(): void
    {
        //
    }
}