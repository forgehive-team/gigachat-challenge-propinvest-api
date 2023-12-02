<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectIndexResource;
use App\Http\Resources\ProjectShowResource;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $limit = 10;
        return response()->json(
            ProjectIndexResource::collection(Project::inRandomOrder()->limit($limit)->get())
        );
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            new ProjectShowResource(Project::find($id))
        );
    }
}
