<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('shops', function (Blueprint $t) {
            $t->string('plan', 32)->default('free')->after('granted_scopes');
            $t->string('charge_id', 191)->nullable()->after('plan');
            $t->string('subscription_status', 32)->nullable()->after('charge_id');
            $t->timestamp('trial_ends_at')->nullable()->after('subscription_status');
        });
    }

    public function down(): void {
        Schema::table('shops', function (Blueprint $t) {
            $t->dropColumn(['plan', 'charge_id', 'subscription_status', 'trial_ends_at']);
        });
    }
};
