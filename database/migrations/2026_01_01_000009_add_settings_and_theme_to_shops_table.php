<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('shops', function (Blueprint $t) {
            $t->json('settings')->nullable()->after('trial_ends_at');
            $t->string('published_theme_id', 64)->nullable()->after('settings');
            $t->string('active_promo_theme_id', 64)->nullable()->after('published_theme_id');
        });
    }

    public function down(): void {
        Schema::table('shops', function (Blueprint $t) {
            $t->dropColumn(['settings', 'published_theme_id', 'active_promo_theme_id']);
        });
    }
};
