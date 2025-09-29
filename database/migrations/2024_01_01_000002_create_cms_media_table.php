<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name');
            $table->string('path');
            $table->string('url');
            $table->string('mime_type');
            $table->string('extension');
            $table->bigInteger('size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->unsignedBigInteger('folder_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->foreign('folder_id')->references('id')->on('cms_folders')->onDelete('set null');
            $table->index('folder_id');
            $table->index('mime_type');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cms_media');
    }
};