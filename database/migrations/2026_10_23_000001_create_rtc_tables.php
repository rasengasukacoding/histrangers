<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rtc_peers', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('status', 12)->default('idle')->index();
            $table->string('partner_id', 64)->nullable();
            $table->uuid('room_id')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'last_seen_at']);
        });

        Schema::create('rtc_signals', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_id')->index();
            $table->string('from_peer_id', 64);
            $table->string('to_peer_id', 64);
            $table->string('type', 16);
            $table->longText('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['to_peer_id', 'room_id', 'id']);
        });

        Schema::create('rtc_exclusions', function (Blueprint $table) {
            $table->id();
            $table->string('left_peer_id', 64);
            $table->string('right_peer_id', 64);
            $table->timestamp('expires_at')->index();
            $table->unique(['left_peer_id', 'right_peer_id']);
        });

        Schema::create('rtc_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reporter_peer_id', 64);
            $table->string('reported_peer_id', 64);
            $table->uuid('room_id');
            $table->string('reason', 40);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rtc_reports');
        Schema::dropIfExists('rtc_exclusions');
        Schema::dropIfExists('rtc_signals');
        Schema::dropIfExists('rtc_peers');
    }
};
