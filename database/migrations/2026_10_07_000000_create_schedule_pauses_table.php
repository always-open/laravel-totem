<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Studio\Totem\Database\TotemMigration;

class CreateSchedulePausesTable extends TotemMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection($this->getConnection())
            ->create($this->prefix().'schedule_pauses', function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('paused_at');
                $table->timestamp('resume_at')->nullable();
                $table->timestamp('resumed_at')->nullable();
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
            ->dropIfExists($this->prefix().'schedule_pauses');
    }
}
