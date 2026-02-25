<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Studio\Totem\Database\TotemMigration;

class CreateTaskResultsTable extends TotemMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection($this->getConnection())
            ->create($this->prefix().'task_results', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('task_id');
                $table->timestamp('ran_at')->useCurrent();
                $table->string('duration');
                $table->longText('result');
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
            ->dropIfExists($this->prefix().'task_results');
    }
}
