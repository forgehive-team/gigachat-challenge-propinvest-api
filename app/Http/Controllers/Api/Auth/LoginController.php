<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\Response;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginService $service
    )
    {
    }

    public function login(LoginRequest $request): \Illuminate\Http\JsonResponse
    {
        $attributes = $request->validated();

        $data = $this->service->login($attributes['email'], $attributes['password'], $attributes['remember'] ?? false);

        if (isset($data['errors'])) {
            return response()->json($data, Response::HTTP_FORBIDDEN);
        }

        return response()->json($data, Response::HTTP_OK);
    }
}
