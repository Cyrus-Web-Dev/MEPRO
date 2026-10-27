<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Pelanggaran aturan bisnis. Otomatis dirender sebagai JSON 422. */
class MesException extends RuntimeException
{
    public function render($request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
