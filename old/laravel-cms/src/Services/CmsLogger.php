<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Support\Facades\Log;

/**
 * Dedicated CMS Logger for tracking all CMS operations
 */
class CmsLogger
{
    protected static function log(string $level, string $message, array $context = [])
    {
        // Add timestamp and request ID for tracking
        $context['timestamp'] = now()->format('Y-m-d H:i:s.u');
        $context['request_id'] = request()->header('X-Request-ID', uniqid('cms_', true));
        $context['user_id'] = auth()->id();
        $context['ip'] = request()->ip();

        // Log to CMS channel
        Log::channel('cms')->{$level}($message, $context);
    }

    public static function info(string $message, array $context = [])
    {
        self::log('info', $message, $context);
    }

    public static function debug(string $message, array $context = [])
    {
        self::log('debug', $message, $context);
    }

    public static function warning(string $message, array $context = [])
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = [])
    {
        self::log('error', $message, $context);
    }

    public static function apiRequest(string $endpoint, array $data)
    {
        self::info("API Request: {$endpoint}", [
            'endpoint' => $endpoint,
            'method' => request()->method(),
            'data' => $data,
            'headers' => request()->headers->all(),
        ]);
    }

    public static function apiResponse(string $endpoint, $response, int $statusCode = 200)
    {
        self::info("API Response: {$endpoint}", [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response' => $response,
        ]);
    }

    public static function fileUpdate(string $action, string $file, array $details = [])
    {
        self::info("File Update: {$action}", array_merge([
            'action' => $action,
            'file' => $file,
            'real_path' => realpath($file),
            'exists' => file_exists($file),
            'writable' => is_writable($file),
        ], $details));
    }

    public static function contentChange(string $key, $oldValue, $newValue, array $metadata = [])
    {
        self::info("Content Change: {$key}", array_merge([
            'key' => $key,
            'old_value' => substr($oldValue, 0, 100),
            'new_value' => substr($newValue, 0, 100),
            'old_length' => strlen($oldValue),
            'new_length' => strlen($newValue),
            'changed' => $oldValue !== $newValue,
        ], $metadata));
    }

    public static function validationError(string $operation, array $errors)
    {
        self::warning("Validation Error: {$operation}", [
            'operation' => $operation,
            'errors' => $errors,
        ]);
    }

    public static function exception(string $operation, \Exception $e)
    {
        self::error("Exception in {$operation}", [
            'operation' => $operation,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}