<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Studio\Totem\Database\TotemMigration;

class AlterTasksTableAddRunInBackgroundSupport extends TotemMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection($this->getConnection())
            ->table($this->prefix().'tasks', function (Blueprint $table) {
                $table->boolean('run_in_background')->default(false);
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
            ->table($this->prefix().'tasks', function (Blueprint $table) {
                $table->dropColumn('run_in_background');
            });
    }
}
