<?php

namespace Webook\LaravelCMS\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Webook\LaravelCMS\Http\Controllers\Controller;

class TranslationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:edit-content']);
    }

    public function index(Request $request, string $locale): JsonResponse
    {
        return response()->json(['locale' => $locale, 'translations' => []]);
    }

    public function show(Request $request, string $locale, string $translationKey): JsonResponse
    {
        return response()->json(['locale' => $locale, 'key' => $translationKey, 'value' => '']);
    }

    public function update(Request $request, string $locale, string $translationKey): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function sync(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function missing(Request $request): JsonResponse
    {
        return response()->json(['missing' => []]);
    }

    public function import(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function export(Request $request, string $locale): JsonResponse
    {
        return response()->json(['locale' => $locale, 'data' => []]);
    }
}