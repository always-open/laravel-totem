<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Studio\Totem\Database\TotemMigration;

class CreateOneOffCommandRunsTable extends TotemMigration
{
    /**
     * Creates the log of allowlisted commands run once from the "Run once" page: who ran which
     * command with which arguments, when, for how long, and what it printed. The `started_at`
     * index serves the newest-first history. `user_id` has no foreign key, so a run outlives
     * its user and the users table may live on another connection.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection($this->getConnection())
            ->create($this->prefix().'one_off_command_runs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('command');
                $table->text('arguments')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email')->nullable();
                $table->timestamp('started_at')->index();
                $table->integer('duration_ms');
                $table->integer('exit_code')->nullable();
                $table->text('output');
                $table->text('error')->nullable();
                $table->timestamps();
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
            ->dropIfExists($this->prefix().'one_off_command_runs');
    }
}
