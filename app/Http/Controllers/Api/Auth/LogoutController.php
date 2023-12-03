<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LogoutService;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    public function __construct(
        private readonly LogoutService $service
    )
    {
    }

    public function logout(): \Illuminate\Http\JsonResponse
    {
        $this->service->logout();

        return response()->json('', Response::HTTP_OK);
    }
}
