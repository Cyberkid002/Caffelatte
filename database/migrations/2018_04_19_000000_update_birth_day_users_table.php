<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBirthDayUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
    */
    public function up() 
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('birthday')->nullable()->default(null);
            $table->string('school')->nullable()->default(null);
            $table->string('degree')->nullable()->default(null);
            $table->text('employer')->nullable()->default(null);
        });
    }
     /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birthday', 'school', 'degree', 'employer']);
        });
    }
}
