<?php

namespace Studio\Totem;

use Illuminate\Database\Eloquent\Model;

class TotemModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('totem.database_connection') ?? config('database.default');
    }

    public function getTable(): string
    {
        $prefix = config('totem.table_prefix', '');
        $table = parent::getTable();

        return str_starts_with($table, $prefix) ? $table : $prefix.$table;
    }
}
