<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\UserService;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service
    )
    {
    }

    public function getUser(): \Illuminate\Http\JsonResponse
    {
        return response()->json(new UserResource($this->service->getUser()), Response::HTTP_OK);
    }
}
