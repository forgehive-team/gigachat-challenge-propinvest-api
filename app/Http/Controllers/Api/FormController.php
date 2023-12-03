<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class FormController extends Controller
{
    const WEBHOOK_URL = 'https://forgehive.ru/webhook/';

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

        // GigaChat AI client
        try {
            $url = self::WEBHOOK_URL . $user->id;
            $response = Http::post($url, $attributes);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json('', Response::HTTP_NO_CONTENT);
    }
}
