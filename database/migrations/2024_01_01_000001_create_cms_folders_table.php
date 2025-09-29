<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cms_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('path')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('cms_folders')->onDelete('cascade');
            $table->index('slug');
            $table->index('parent_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cms_folders');
    }
};