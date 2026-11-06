<?php

namespace Studio\Totem\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Studio\Totem\Tests\TestCase;

/**
 * The one-off command run log's schema, read through Laravel's schema builder so it runs on every
 * driver: exactly the specified columns and nullability, the `started_at` index the newest-first
 * history reads, and no foreign key.
 */
class OneOffCommandRunsSchemaTest extends TestCase
{
    public function test_has_exactly_the_specified_columns_and_nullability(): void
    {
        $columns = collect(Schema::getColumns('one_off_command_runs'))
            ->mapWithKeys(fn (array $column) => [$column['name'] => $column['nullable']])
            ->sortKeys()
            ->all();

        $this->assertSame([
            'arguments' => true,
            'command' => false,
            'created_at' => true,
            'duration_ms' => false,
            'error' => true,
            'exit_code' => true,
            'id' => false,
            'output' => false,
            'started_at' => false,
            'updated_at' => true,
            'user_email' => true,
            'user_id' => true,
        ], $columns);
    }

    public function test_uses_portable_column_types(): void
    {
        $types = collect(Schema::getColumns('one_off_command_runs'))
            ->mapWithKeys(fn (array $column) => [$column['name'] => $column['type_name']]);

        // Type names as SQLite, MySQL and Postgres report them.
        $this->assertContains($types['duration_ms'], ['integer', 'int', 'int4']);
        $this->assertContains($types['exit_code'], ['integer', 'int', 'int4']);
        $this->assertContains($types['output'], ['text']);
        $this->assertContains($types['arguments'], ['text']);
        $this->assertContains($types['error'], ['text']);
        // A plain timestamp, like every other Totem table: never timestamptz.
        $this->assertContains($types['started_at'], ['datetime', 'timestamp']);
    }

    public function test_indexes_started_at_for_the_newest_first_history(): void
    {
        $startedAtIndexes = collect(Schema::getIndexes('one_off_command_runs'))
            ->filter(fn (array $index) => $index['columns'] === ['started_at']);

        $this->assertCount(1, $startedAtIndexes);
    }

    public function test_carries_no_foreign_key(): void
    {
        $this->assertTrue(Schema::hasTable('one_off_command_runs'));
        $this->assertSame([], Schema::getForeignKeys('one_off_command_runs'));
    }

    public function test_drops_the_table_on_rollback(): void
    {
        $migration = require_once __DIR__.'/../../database/migrations/2026_11_05_000000_create_one_off_command_runs_table.php';
        $migration = is_object($migration) ? $migration : new \CreateOneOffCommandRunsTable;

        $migration->down();
        $this->assertFalse(Schema::hasTable('one_off_command_runs'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('one_off_command_runs'));
    }
}
