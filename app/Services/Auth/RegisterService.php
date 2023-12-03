<?php

namespace App\Services\Auth;

class RegisterService
{
    public function __construct(
        readonly private UserService $userService
    )
    {
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $password
     * @return void
     * @throws \Exception
     */
    public function register(
        string $name,
        string $email,
        string $password
    ): array
    {
        $user = $this->userService->createUser(
            $name,
            $email,
            $password,
        );
        $token = $user->createToken('auth-token')->plainTextToken;
        return ['token' => $token];
    }
}
