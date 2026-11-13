<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pul yozadigan so'rovlar uchun: bir xil `Idempotency-Key` bilan kelgan takroriy
 * so'rov qayta bajarilmaydi, birinchi javobning o'zi qaytariladi.
 */
class Idempotent
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '' || strlen($key) > 100) {
            return response()->json([
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                'message' => 'Idempotency-Key sarlavhasi kerak.',
            ], 400);
        }

        $userId = $request->user()?->getAuthIdentifier();
        $where = ['user_id' => $userId, 'key' => $key];

        try {
            DB::table('idempotency_keys')->insert([...$where, 'path' => $request->path(), 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            $stored = DB::table('idempotency_keys')->where($where)->first();

            // Birinchi so'rov hali tugamagan.
            if ($stored === null || $stored->status === null) {
                return response()->json([
                    'code' => 'REQUEST_IN_PROGRESS',
                    'message' => "Bu so'rov hozir bajarilmoqda.",
                ], 409);
            }

            return response((string) $stored->response, (int) $stored->status, [
                'Content-Type' => 'application/json',
                'Idempotent-Replayed' => 'true',
            ]);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            DB::table('idempotency_keys')->where($where)->delete();

            throw $exception;
        }

        // Server xatosida kalit bo'shatiladi: ilova o'sha kalit bilan qayta urinishi mumkin.
        if ($response->getStatusCode() >= 500) {
            DB::table('idempotency_keys')->where($where)->delete();
        } else {
            DB::table('idempotency_keys')->where($where)->update([
                'status' => $response->getStatusCode(),
                'response' => $response->getContent(),
            ]);
        }

        return $response;
    }
}
