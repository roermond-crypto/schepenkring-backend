<?php

namespace App\Http\Controllers;

use App\Services\LocaleService;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function index(Request $request, LocaleService $locales)
    {
        return response()->json([
            'default' => $locales->default(),
            'supported' => $locales->supported(),
            'fallbacks' => config('locales.fallbacks', []),
        ]);
    }
}
