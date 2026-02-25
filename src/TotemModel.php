<?php

namespace Studio\Totem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TotemModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('totem.database_connection', config('database.default'));
    }

    public function getTable(): string
    {
        $prefix = config('totem.table_prefix', '');
        $table = parent::getTable();

        return Str::startsWith($table, $prefix) ? $table : $prefix.$table;
    }
}
