<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->enum('request_type', ['driver', 'productpickup', 'staffrescue']);
            $table->enum('urgency', ['Regular', 'Urgent']);
            $table->unsignedBigInteger('driver_id'); //ASSIGNED_DRIVER
            $table->unsignedBigInteger('car_id');
            $table->unsignedBigInteger('user_id'); // REQUESTER
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('time');
            $table->text('image_url');
            $table->enum('status', ['pending' ,'approved', 'rejected', 'completed'])->default('pending');
            $table->enum('driver_status', ['pending' , 'assigned', 'accepted', 'done'])->default('pending');
            $table->date('approval_date')->nullable();
            $table->timestamp('approval_time')->nullable();
            $table->timestamps();

            // Relationships
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('car_id')->references('id')->on('cars')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
