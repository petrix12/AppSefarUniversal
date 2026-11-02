<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class JotformCouponCronController extends Controller
{
    private const FORM_ID = '242624572998370';

    public function __invoke(Request $request): JsonResponse
    {
        $exitCode = Artisan::call('jotform:obtener-datos', [
            'formId' => self::FORM_ID,
            '--no-interaction' => true,
        ]);

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'form_id' => self::FORM_ID,
            'output' => trim(Artisan::output()),
            'time' => now()->toDateTimeString(),
        ], $exitCode === 0 ? 200 : 500);
    }
}
