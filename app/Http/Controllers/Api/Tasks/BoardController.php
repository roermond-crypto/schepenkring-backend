<?php

namespace App\Http\Controllers\Api\Tasks;

use App\Actions\Tasks\GetBoardAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function index(Request $request, GetBoardAction $action)
    {
        $board = $action->execute($request->user(), $request->integer('location_id'));

        return response()->json($board);
    }
}
