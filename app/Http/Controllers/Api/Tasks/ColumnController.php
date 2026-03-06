<?php

namespace App\Http\Controllers\Api\Tasks;

use App\Actions\Tasks\CreateColumnAction;
use App\Actions\Tasks\DeleteColumnAction;
use App\Actions\Tasks\ReorderColumnsAction;
use App\Actions\Tasks\UpdateColumnAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tasks\ColumnReorderRequest;
use App\Http\Requests\Api\Tasks\ColumnStoreRequest;
use App\Http\Requests\Api\Tasks\ColumnUpdateRequest;
use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\Request;

class ColumnController extends Controller
{
    public function store(ColumnStoreRequest $request, CreateColumnAction $action)
    {
        $board = Board::findOrFail($request->validated()['board_id']);
        $column = $action->execute($request->user(), $board, $request->validated());

        return response()->json($column, 201);
    }

    public function update(ColumnUpdateRequest $request, int $id, UpdateColumnAction $action)
    {
        $column = Column::findOrFail($id);
        $updated = $action->execute($request->user(), $column, $request->validated());

        return response()->json($updated);
    }

    public function destroy(int $id, Request $request, DeleteColumnAction $action)
    {
        $column = Column::findOrFail($id);
        $action->execute($request->user(), $column);

        return response()->json(['message' => 'Deleted']);
    }

    public function reorder(ColumnReorderRequest $request, ReorderColumnsAction $action)
    {
        $action->execute($request->user(), $request->validated()['columns']);

        return response()->json(['message' => 'Columns reordered']);
    }
}
