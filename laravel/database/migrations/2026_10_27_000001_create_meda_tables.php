<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('locations',function(Blueprint $t){$t->id();$t->string('region');$t->string('subcity');$t->string('woreda');$t->timestamps();$t->unique(['region','subcity','woreda']);});
  Schema::create('users',function(Blueprint $t){$t->id();$t->string('national_id',50)->unique();$t->string('first_name',100);$t->string('last_name',100)->default('');$t->string('phone',20)->nullable()->unique();$t->string('email',150)->nullable()->unique();$t->string('username')->nullable()->unique();$t->string('password')->nullable();$t->date('dob')->nullable();$t->text('photo')->nullable();$t->decimal('store_credit',12,2)->default(0);$t->string('role',30)->default('USER');$t->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();$t->boolean('active')->default(true);$t->rememberToken();$t->timestamps();});
  DB::statement("ALTER TABLE users ADD CONSTRAINT nonnegative_store_credit CHECK(store_credit>=0)");
  Schema::create('fields',function(Blueprint $t){$t->id();$t->foreignId('location_id')->constrained()->restrictOnDelete();$t->foreignId('operator_user_id')->nullable()->constrained('users')->nullOnDelete();$t->string('name',150);$t->string('type',50)->default('FOOTBALL');$t->text('description')->nullable();$t->string('address')->nullable();$t->decimal('price_per_hour',12,2);$t->string('status',20)->default('ACTIVE');$t->jsonb('operating_hours');$t->timestamps();});
  Schema::create('bookings',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('user_id')->constrained()->restrictOnDelete();$t->foreignId('field_id')->constrained()->restrictOnDelete();$t->timestampTz('start_time');$t->timestampTz('end_time');$t->timestampTz('reserved_until');$t->string('status',30);$t->string('team',60);$t->decimal('amount',12,2);$t->decimal('credit_applied',12,2)->default(0);$t->decimal('telebirr_amount',12,2);$t->string('payment_ref')->nullable()->unique();$t->string('qr_hash',64)->nullable()->unique();$t->timestampTz('checked_in_at')->nullable();$t->timestampTz('expires_at')->nullable();$t->string('lock_owner')->nullable();$t->uuid('idempotency_key');$t->timestamps();$t->unique(['user_id','idempotency_key']);$t->index(['field_id','start_time']);});
  DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
  DB::statement("ALTER TABLE bookings ADD CONSTRAINT valid_play_range CHECK(end_time=start_time+interval '60 minutes' AND reserved_until=end_time+interval '15 minutes')");
  DB::statement("ALTER TABLE bookings ADD CONSTRAINT booking_money_balances CHECK(amount>=0 AND credit_applied>=0 AND telebirr_amount>=0 AND amount=credit_applied+telebirr_amount)");
  DB::statement("ALTER TABLE bookings ADD CONSTRAINT no_overlapping_reservations EXCLUDE USING gist (field_id WITH =, tstzrange(start_time,reserved_until,'[)') WITH &&) WHERE (status IN ('PENDING_PAYMENT','PAID','CHECKED_IN','COMPLETED'))");
  Schema::create('credits',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->restrictOnDelete();$t->uuid('booking_id');$t->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();$t->decimal('amount',12,2);$t->string('reason',30);$t->timestamps();$t->unique(['booking_id','reason']);});
  Schema::create('payment_events',function(Blueprint $t){$t->id();$t->string('provider_ref')->unique();$t->uuid('booking_id');$t->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();$t->decimal('amount',12,2);$t->string('currency',3);$t->string('status',30);$t->timestamps();});
  Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$t->string('action');$t->string('subject');$t->jsonb('details')->nullable();$t->timestamps();});
  Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
  Schema::create('failed_jobs',function(Blueprint $t){$t->id();$t->string('uuid')->unique();$t->text('connection');$t->text('queue');$t->longText('payload');$t->longText('exception');$t->timestamp('failed_at')->useCurrent();});
 }
 public function down(): void {foreach(['failed_jobs','password_reset_tokens','audit_logs','payment_events','credits','bookings','fields','users','locations'] as $table)Schema::dropIfExists($table);}
};
