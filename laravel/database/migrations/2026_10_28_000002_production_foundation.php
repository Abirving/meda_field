<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('national_id', 50)->nullable()->change();
            $t->timestampTz('email_verified_at')->nullable();
            $t->timestampTz('terms_accepted_at')->nullable();
            $t->string('terms_version', 30)->nullable();
            $t->string('language', 8)->default('en');
            $t->unsignedInteger('session_version')->default(1);
            $t->text('totp_secret')->nullable();
            $t->text('recovery_codes')->nullable();
            $t->bigInteger('totp_last_step')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX users_email_case_unique ON users (lower(email)) WHERE email IS NOT NULL');
        Schema::create('verified_identities', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('provider', 30); $t->string('subject_hash', 64); $t->timestamps();
            $t->unique(['provider','subject_hash']); $t->unique(['user_id','provider']);
        });
        Schema::table('fields', fn(Blueprint $t) => $t->jsonb('weekly_hours')->nullable());
        Schema::create('field_blocks', function (Blueprint $t) {
            $t->id(); $t->foreignId('field_id')->constrained()->cascadeOnDelete();
            $t->timestampTz('starts_at'); $t->timestampTz('ends_at'); $t->string('reason', 250);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
            $t->index(['field_id','starts_at','ends_at']);
        });
        DB::statement('ALTER TABLE field_blocks ADD CONSTRAINT valid_block_range CHECK(ends_at > starts_at)');
        Schema::table('bookings', function (Blueprint $t) {
            $t->timestampTz('reminder_sent_at')->nullable(); $t->string('qr_key_id', 30)->nullable(); $t->string('closure_reason', 250)->nullable();
        });
        DB::statement("ALTER TABLE bookings DROP CONSTRAINT no_overlapping_reservations");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT no_overlapping_reservations EXCLUDE USING gist (field_id WITH =, tstzrange(start_time,reserved_until,'[)') WITH &&) WHERE (status IN ('PENDING_PAYMENT','PAID','CHECKED_IN','COMPLETED','NO_SHOW'))");
        Schema::create('payment_attempts', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('booking_id')->unique();
            $t->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $t->string('merchant_order_id', 64)->unique(); $t->string('provider_order_id')->nullable();
            $t->decimal('amount',12,2); $t->string('currency',3)->default('ETB');
            $t->string('state',30)->default('CREATED'); $t->text('checkout_url')->nullable();
            $t->timestampTz('expires_at'); $t->timestampTz('last_checked_at')->nullable();
            $t->unsignedInteger('query_count')->default(0); $t->string('last_error',100)->nullable(); $t->timestamps();
        });
        Schema::create('payment_receipts', function (Blueprint $t) {
            $t->id(); $t->uuid('payment_attempt_id');
            $t->foreign('payment_attempt_id')->references('id')->on('payment_attempts')->restrictOnDelete();
            $t->string('fingerprint',64)->unique(); $t->string('provider_ref');
            $t->string('outcome',30); $t->string('source',20); $t->decimal('amount',12,2);
            $t->string('currency',3); $t->timestampTz('received_at');
        });
        Schema::create('payment_reviews', function (Blueprint $t) {
            $t->id(); $t->uuid('booking_id'); $t->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $t->string('provider_ref')->unique(); $t->decimal('amount',12,2); $t->string('reason',100);
            $t->string('status',20)->default('OPEN'); $t->foreignId('resolved_by')->nullable()->constrained('users');
            $t->text('resolution')->nullable(); $t->string('evidence_reference')->nullable();
            $t->timestampTz('resolved_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS users_email_case_unique');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT no_overlapping_reservations');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT no_overlapping_reservations EXCLUDE USING gist (field_id WITH =, tstzrange(start_time,reserved_until,'[)') WITH &&) WHERE (status IN ('PENDING_PAYMENT','PAID','CHECKED_IN','COMPLETED'))");
        foreach(['payment_reviews','payment_receipts','payment_attempts','field_blocks','verified_identities'] as $table) Schema::dropIfExists($table);
        Schema::table('bookings', fn(Blueprint $t)=>$t->dropColumn(['qr_key_id','closure_reason','reminder_sent_at']));
        Schema::table('fields', fn(Blueprint $t)=>$t->dropColumn('weekly_hours'));
        Schema::table('users', fn(Blueprint $t)=>$t->dropColumn(['email_verified_at','terms_accepted_at','terms_version','language','session_version','totp_secret','recovery_codes','totp_last_step']));
    }
};
