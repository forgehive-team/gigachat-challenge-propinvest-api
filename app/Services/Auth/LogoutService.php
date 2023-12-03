<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogoutService
{
    public function logout(): void
    {
        try {
            $user = Auth::user();
            $user->currentAccessToken()->delete();
            // @todo: remove all access tokens
            // $user->tokens()->delete();
        } catch (\Exception $exception) {
            Log::error(__METHOD__ . " Something happened while removing access tokens from the user", [
                'exception-message' => $exception->getMessage(),
            ]);
        }
    }
}
