<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('shops', function (Blueprint $t) { $t->id(); $t->string('shop_domain',255)->unique(); $t->text('access_token'); $t->text('refresh_token')->nullable(); $t->timestamp('token_expires_at')->nullable(); $t->timestamp('refresh_token_expires_at')->nullable(); $t->string('granted_scopes')->nullable(); $t->timestamp('installed_at')->nullable(); $t->timestamp('uninstalled_at')->nullable(); $t->timestamps(); }); } public function down(): void { Schema::dropIfExists('shops'); } };
