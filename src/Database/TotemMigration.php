<?php

namespace Studio\Totem\Database;

use Illuminate\Database\Migrations\Migration;

abstract class TotemMigration extends Migration
{
    public function getConnection(): ?string
    {
        return config('totem.database_connection', config('database.default'));
    }

    protected function prefix(): string
    {
        return config('totem.table_prefix', '');
    }
}
