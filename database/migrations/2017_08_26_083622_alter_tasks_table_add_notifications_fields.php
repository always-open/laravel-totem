<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Studio\Totem\Database\TotemMigration;

class AlterTasksTableAddNotificationsFields extends TotemMigration
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
                $table->string('notification_phone_number')->nullable()->after('notification_email_address');
                $table->string('notification_slack_webhook')->nullable()->after('notification_phone_number');
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
                $table->dropColumn('notification_phone_number');
            });

        Schema::connection($this->getConnection())
            ->table($this->prefix().'tasks', function (Blueprint $table) {
                $table->dropColumn('notification_slack_webhook');
            });
    }
}
