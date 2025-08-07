<?php

namespace App\Contracts;

interface UserResolver
{
    /**
     * Get the current user ID
     */
    public function getUserId(): int;

    /**
     * Get the current user name
     */
    public function getUserName(): string;

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool;
}