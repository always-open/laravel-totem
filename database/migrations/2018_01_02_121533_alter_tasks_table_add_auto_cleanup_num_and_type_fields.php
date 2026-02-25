<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Studio\Totem\Database\TotemMigration;

class AlterTasksTableAddAutoCleanupNumAndTypeFields extends TotemMigration
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
                $table->integer('auto_cleanup_num')->default(0);
                $table->string('auto_cleanup_type', 20)->nullable();
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
                $table->dropColumn('auto_cleanup_num');
            });

        Schema::connection($this->getConnection())
            ->table($this->prefix().'tasks', function (Blueprint $table) {
                $table->dropColumn('auto_cleanup_type');
            });
    }
}
