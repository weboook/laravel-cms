<?php

namespace Webook\LaravelCMS\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Webook\LaravelCMS\Http\Controllers\Controller;

class AssetController extends Controller
{
    public function __construct()
    {
        // Only apply auth middleware if CMS auth is required
        if (config('cms.api.auth.required', false)) {
            $this->middleware(['auth', 'can:manage-assets']);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'asset' => ['id' => 1, 'url' => '/uploads/test.jpg']]);
    }

    public function browse(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => (int) $page,
                'last_page' => 1,
                'per_page' => (int) $perPage,
                'total' => 0,
                'from' => 0,
                'to' => 0
            ]
        ]);
    }

    public function show(Request $request, $asset): JsonResponse
    {
        return response()->json(['id' => $asset, 'url' => '/uploads/test.jpg']);
    }

    public function update(Request $request, $asset): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, $asset): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function optimize(Request $request, $asset): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function search(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        return response()->json([
            'data' => [],
            'meta' => [
                'current_page' => (int) $page,
                'last_page' => 1,
                'per_page' => (int) $perPage,
                'total' => 0,
                'from' => 0,
                'to' => 0
            ]
        ]);
    }
}