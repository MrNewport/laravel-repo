<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('repositories', fn (Blueprint $table) => $table->boolean('is_private')->default(true));
    }

    public function down(): void
    {
        Schema::table('repositories', fn (Blueprint $table) => $table->dropColumn('is_private'));
    }
};
