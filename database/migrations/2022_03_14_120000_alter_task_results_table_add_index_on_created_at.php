<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Studio\Totem\Database\TotemMigration;

class AlterTaskResultsTableAddIndexOnCreatedAt extends TotemMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(TOTEM_DATABASE_CONNECTION)
            ->table(TOTEM_TABLE_PREFIX.'task_results', function (Blueprint $table) {
                $table->index('created_at');
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(TOTEM_DATABASE_CONNECTION)
            ->table(TOTEM_TABLE_PREFIX.'task_results', function (Blueprint $table) {
                $table->dropIndex('created_at');
            });
    }
}
