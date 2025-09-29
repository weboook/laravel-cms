<?php

namespace Webook\LaravelCMS\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Webook\LaravelCMS\Http\Controllers\Controller;

class HistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:view-history']);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['history' => [], 'total' => 0]);
    }

    public function show(Request $request, $history): JsonResponse
    {
        return response()->json(['id' => $history, 'changes' => []]);
    }

    public function diff(Request $request, $history): JsonResponse
    {
        return response()->json(['diff' => '']);
    }

    public function contentHistory(Request $request, string $contentKey): JsonResponse
    {
        return response()->json(['content_key' => $contentKey, 'history' => []]);
    }

    public function restore(Request $request, $history): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function backups(Request $request): JsonResponse
    {
        return response()->json(['backups' => []]);
    }

    public function createBackup(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'backup_id' => 1]);
    }

    public function restoreBackup(Request $request, $backup): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function deleteBackup(Request $request, $backup): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function downloadBackup(Request $request, $backup)
    {
        return response()->download(storage_path('app/backup.zip'));
    }
}