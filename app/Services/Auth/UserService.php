<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function getUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        try {
            return Auth::user();
        } catch (\Exception $exception) {
            Log::error(__METHOD__ . " User is unauthorized", [
                'exception-message' => $exception->getMessage(),
            ]);
        }
        return null;
    }

    public function createUser(
        string $name,
        string $email,
        string $password,
    ): User
    {
        return User::create([
            'name'          => $name,
            'email'         => $email,
            'password'      => Hash::make($password),
        ]);
    }
}
