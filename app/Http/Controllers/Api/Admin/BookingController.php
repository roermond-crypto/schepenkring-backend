<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::query()->with(['location', 'boat']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($request->has('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }
        
        if ($request->has('boat_id')) {
            $query->where('boat_id', $request->input('boat_id'));
        }

        if ($request->has('status') && strtolower($request->input('status')) !== 'all') {
            $query->where('status', strtolower($request->input('status')));
        }

        $sortBy = $request->input('sort_by', 'created_at');
        // allow some basic fields
        $allowedSorts = ['created_at', 'date', 'name', 'status', 'type'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) $request->integer('per_page', 25);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Display the specified booking.
     */
    public function show($id): JsonResponse
    {
        $booking = Booking::with(['location', 'boat'])->findOrFail($id);
        
        return response()->json($booking);
    }
}
