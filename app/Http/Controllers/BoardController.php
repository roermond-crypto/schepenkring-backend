<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function index(Request $request)
    {
        // For simplicity, we have a single global board
        $board = Board::with(['columns' => function($q) {
            $q->orderBy('position');
        }])->first();

        if (!$board) {
            $board = Board::create(['name' => 'Main Board']);
            Column::insert([
                ['board_id' => $board->id, 'name' => 'To Do', 'position' => 0],
                ['board_id' => $board->id, 'name' => 'In Progress', 'position' => 1],
                ['board_id' => $board->id, 'name' => 'Done', 'position' => 2],
            ]);
            $board->load(['columns' => function($q) {
                $q->orderBy('position');
            }]);
        }

        return response()->json($board);
    }
}
