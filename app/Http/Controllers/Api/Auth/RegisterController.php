<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\Auth\RegisterService;
use Illuminate\Http\Response;

class RegisterController extends Controller
{
    public function __construct(
        readonly private RegisterService $service
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function register(RegisterRequest $request): \Illuminate\Http\JsonResponse
    {
        $attributes = $request->validated();
        $this->service->register(
            $attributes['name'],
            $attributes['email'],
            $attributes['password']
        );
        return response()->json('', Response::HTTP_OK);
    }
}
