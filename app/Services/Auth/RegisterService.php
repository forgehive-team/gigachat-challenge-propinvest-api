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
    ): void
    {
        $this->userService->createUser(
            $name,
            $email,
            $password,
        );
    }
}
