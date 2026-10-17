<?php

use App\Addons\AddonRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hostel Management tables in each school's database. A copy of this file lives in
 * database/migrations/schools so new schools get it when they are provisioned.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hostel_blocks')) {
            Schema::create('hostel_blocks', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('code', 32)->nullable();
                $table->string('type', 8)->default('Boys'); // Boys, Girls, Coed, Staff
                $table->unsignedTinyInteger('total_floors')->default(1);
                $table->unsignedBigInteger('warden_id')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hostel_room_types')) {
            Schema::create('hostel_room_types', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('fee_cycle', 10)->default('Monthly'); // Monthly, Quarterly, Annual
                $table->decimal('base_fee', 10, 2)->default(0);
                $table->json('amenities')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hostel_rooms')) {
            Schema::create('hostel_rooms', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('block_id')->constrained('hostel_blocks')->cascadeOnDelete();
                $table->foreignId('room_type_id')->nullable()->constrained('hostel_room_types')->nullOnDelete();
                $table->string('room_number', 32);
                $table->unsignedTinyInteger('floor')->default(0);
                $table->unsignedTinyInteger('bed_capacity')->default(1);
                $table->string('status', 12)->default('Available'); // Available, Maintenance
                $table->timestamps();
                $table->unique(['block_id', 'room_number']);
            });
        }

        if (!Schema::hasTable('hostel_beds')) {
            Schema::create('hostel_beds', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('room_id')->constrained('hostel_rooms')->cascadeOnDelete();
                $table->string('bed_number', 32);
                $table->unsignedBigInteger('current_allocation_id')->nullable()->index();
                $table->timestamps();
                $table->unique(['room_id', 'bed_number']);
            });
        }

        if (!Schema::hasTable('hostel_allocations')) {
            Schema::create('hostel_allocations', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('student_id')->index(); // students.id
                $table->unsignedBigInteger('user_id')->index();
                $table->foreignId('bed_id')->constrained('hostel_beds')->cascadeOnDelete();
                $table->unsignedBigInteger('session_year_id')->nullable();
                $table->date('allocation_date');
                $table->date('vacate_date')->nullable();
                $table->string('status', 12)->default('Active')->index(); // Active, Vacated, Transferred
                $table->decimal('fee_amount', 10, 2)->nullable(); // overrides the room type fee
                $table->string('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hostel_attendances')) {
            Schema::create('hostel_attendances', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('block_id')->constrained('hostel_blocks')->cascadeOnDelete();
                $table->unsignedBigInteger('student_id');
                $table->date('date');
                $table->string('roll_call', 8)->default('Night'); // Morning, Night
                $table->string('status', 10); // Present, Absent, On_Leave, Late
                $table->unsignedBigInteger('marked_by')->nullable();
                $table->string('remarks')->nullable();
                $table->timestamps();
                $table->unique(['student_id', 'date', 'roll_call']);
            });
        }

        if (!Schema::hasTable('hostel_gate_passes')) {
            Schema::create('hostel_gate_passes', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->string('pass_type', 12)->default('Day_Out'); // Day_Out, Night_Out, Home_Visit
                $table->string('reason')->nullable();
                $table->dateTime('out_time');
                $table->dateTime('expected_in_time');
                $table->dateTime('actual_out_time')->nullable();
                $table->dateTime('actual_in_time')->nullable();
                $table->string('guardian_status', 10)->default('Pending'); // Pending, Approved, Rejected
                $table->string('warden_status', 10)->default('Pending');
                $table->string('status', 10)->default('Requested')->index(); // Requested, Approved, Rejected, Out, Returned, Cancelled
                $table->string('code', 12)->unique();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hostel_visitors')) {
            Schema::create('hostel_visitors', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->string('visitor_name');
                $table->string('relation', 64)->nullable();
                $table->string('contact', 32)->nullable();
                $table->string('id_proof', 64)->nullable();
                $table->string('purpose')->nullable();
                $table->dateTime('in_time');
                $table->dateTime('out_time')->nullable();
                $table->unsignedBigInteger('logged_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hostel_fees')) {
            Schema::create('hostel_fees', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('allocation_id')->constrained('hostel_allocations')->cascadeOnDelete();
                $table->unsignedBigInteger('student_id')->index();
                $table->string('period_label', 32);
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('amount', 10, 2);
                $table->date('due_date');
                $table->string('status', 8)->default('Unpaid')->index(); // Unpaid, Paid, Waived
                $table->string('payment_mode', 16)->nullable();
                $table->string('receipt_no', 32)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('collected_by')->nullable();
                $table->timestamps();
                $table->unique(['allocation_id', 'period_start']);
            });
        }

        AddonRegistry::syncSchoolPermissions();
    }

    public function down(): void
    {
        foreach (['hostel_fees', 'hostel_visitors', 'hostel_gate_passes', 'hostel_attendances', 'hostel_allocations',
                     'hostel_beds', 'hostel_rooms', 'hostel_room_types', 'hostel_blocks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
