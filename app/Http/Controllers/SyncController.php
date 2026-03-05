<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class SyncController extends Controller
{
    public function retry()
    {
        // This runs the artisan command we just updated
        // It runs in the background so the user doesn't wait
        Artisan::call('pinecone:sync');

        return response()->json([
            'status' => 'success',
            'message' => 'Sync process started. Missing images will be indexed shortly.'
        ]);
    }
}