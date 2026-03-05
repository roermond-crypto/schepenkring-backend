<?php

namespace App\Repositories;

use App\Models\Column;

class ColumnRepository
{
    public function create(array $data): Column
    {
        return Column::create($data);
    }

    public function update(Column $column, array $data): Column
    {
        $column->fill($data);
        $column->save();

        return $column;
    }
}
