<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FormController extends Controller
{
    public function __construct(
        private readonly UserService $service
    )
    {
    }

    public function submit(Request $request): \Illuminate\Http\JsonResponse
    {
        $attributes = $request->all();
        $user = $this->service->getUser();
        $user->update(['form' => json_encode($attributes)]);
        // @todo: send to AI webhook
        return response()->json('', Response::HTTP_NO_CONTENT);
    }
}
