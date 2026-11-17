<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('item_id')->index('item_reviews_item_id_index');
            $table->bigInteger('dp_user_id')->nullable()->index('item_reviews_dp_user_id_index');
            $table->unsignedBigInteger('store_order_id')->nullable()->index('item_reviews_store_order_id_index');
            $table->string('username');
            $table->tinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->text('response')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_editable')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'item_id'], 'item_reviews_store_item_index');
        });

        Schema::create('item_review_images', function (Blueprint $table) {
            $table->char('uuid', 36)->primary();
            $table->unsignedBigInteger('item_review_id')->index('item_review_images_item_review_id_index');
            $table->string('url');
            $table->string('type')->nullable();
            $table->string('processed')->nullable();
        });

        Schema::create('item_review_report_reasons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 255);
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_review_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('item_review_id')->index('item_review_reports_review_index');
            $table->bigInteger('dp_user_id')->nullable()->index('item_review_reports_user_index');
            $table->unsignedBigInteger('item_review_report_reason_id')->index('item_review_reports_reason_index');
            $table->timestamps();
        });

        Schema::create('dp_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user_uuid')->nullable();
            $table->string('username')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('profile_thumb')->nullable();
        });

        Schema::create('chat_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->bigInteger('user_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('threads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->nullable()->unique('threads_uuid_unique');
            $table->string('customer_id', 255)->index('threads_customer_index');
            $table->string('seller_id', 255);
            $table->boolean('is_active')->default(true);
            $table->string('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'last_message_at'], 'threads_seller_last_message_index');
        });

        Schema::create('chats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->nullable();
            $table->char('thread_uuid', 36)->nullable()->index('chats_thread_uuid_index');
            $table->string('sender_id', 255);
            $table->text('message');
            $table->tinyInteger('is_read')->default(0);
            $table->timestamps();

            $table->index(['thread_uuid', 'created_at'], 'chats_thread_created_index');
            $table->index(['thread_uuid', 'is_read'], 'chats_thread_unread_index');
        });

        Schema::create('seller_chat_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('thread_uuid')->index('seller_chat_reports_thread_uuid_index');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_chat_reports');
        Schema::dropIfExists('chats');
        Schema::dropIfExists('threads');
        Schema::dropIfExists('chat_users');
        Schema::dropIfExists('dp_users');
        Schema::dropIfExists('item_review_reports');
        Schema::dropIfExists('item_review_report_reasons');
        Schema::dropIfExists('item_review_images');
        Schema::dropIfExists('item_reviews');
    }
};
