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
        Schema::connection($this->getConnection())
            ->table($this->prefix().'task_results', function (Blueprint $table) {
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
        Schema::connection($this->getConnection())
            ->table($this->prefix().'task_results', function (Blueprint $table) {
                $table->dropIndex($this->prefix().'task_results_created_at_index');
            });
    }
}
