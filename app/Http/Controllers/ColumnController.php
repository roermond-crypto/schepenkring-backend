<?php

namespace App\Http\Controllers;

use App\Models\Column;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ColumnController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'board_id' => 'required|exists:boards,id',
            'name' => 'required|string',
            'position' => 'required|integer'
        ]);

        $column = Column::create($request->all());
        return response()->json($column, 201);
    }

    public function update(Request $request, $id)
    {
        $column = Column::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|string',
            'position' => 'sometimes|integer'
        ]);

        $column->update($request->only(['name', 'position']));
        return response()->json($column);
    }

    public function destroy($id)
    {
        Column::destroy($id);
        return response()->json(['message' => 'Deleted']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'columns' => 'required|array',
            'columns.*.id' => 'required|exists:columns,id',
            'columns.*.position' => 'required|integer',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->columns as $colData) {
                Column::where('id', $colData['id'])->update(['position' => $colData['position']]);
            }
        });

        return response()->json(['message' => 'Columns reordered']);
    }
}
