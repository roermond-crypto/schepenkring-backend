<?php

namespace App\Http\Controllers\Api\Tasks;

use App\Actions\Tasks\ListTaskAssigneesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TaskUserController extends Controller
{
    public function employees(Request $request, ListTaskAssigneesAction $action)
    {
        $employees = $action->execute($request->user());

        return response()->json($employees);
    }
}
