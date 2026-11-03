<?php

namespace App\Services\Interface;

use App\Models\User;

interface AuthenticationServiceInterface
{
    /**
     * @return array{accessToken:string,refreshToken:string,user:array<string,mixed>}
     */
    public function register(array $input, array $meta = []): array;

    /**
     * @return array{accessToken:string,refreshToken:string,user:array<string,mixed>}
     */
    public function loginWithPassword(string $username, string $password, array $meta = []): array;

    /**
     * @return array{accessToken:string,refreshToken:string}
     */
    public function refresh(string $refreshToken, ?string $organizationId = null, array $meta = []): array;

    public function logout(User $user, ?string $refreshToken = null): void;

    public function requestOTPForUsername(string $username): bool;

    /**
     * @return array{accessToken:string,refreshToken:string,user:array<string,mixed>}
     */
    public function verifyOTP(string $username, string $code, array $meta = []): array;
}
