<?php

namespace App\Http\Controllers;

use App\Models\BoatType;
use Illuminate\Http\JsonResponse;

class BoatTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(BoatType::all());
    }
}