<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('oauth_states', function(Blueprint $t){$t->id();$t->string('state_hash',64)->unique();$t->string('shop_domain',255);$t->timestamp('expires_at')->index();$t->timestamps();}); } public function down(): void {Schema::dropIfExists('oauth_states');} };
