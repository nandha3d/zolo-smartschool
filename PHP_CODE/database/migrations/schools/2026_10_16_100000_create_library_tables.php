<?php

use App\Addons\AddonRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Library Management tables in each school's database. A copy of this file lives in
 * database/migrations/schools so new schools get it when they are provisioned.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('library_categories')) {
            Schema::create('library_categories', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->string('name');
                $table->string('code', 32)->nullable();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('library_books')) {
            Schema::create('library_books', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('category_id')->nullable()->constrained('library_categories')->nullOnDelete();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('author')->nullable();
                $table->string('publisher')->nullable();
                $table->string('isbn', 20)->nullable()->index();
                $table->string('edition', 64)->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable();
                $table->string('language', 64)->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('dewey_decimal_code', 32)->nullable();
                $table->string('rack_location', 64)->nullable();
                $table->string('shelf_number', 64)->nullable();
                $table->boolean('is_digital')->default(false);
                $table->string('digital_file_path')->nullable();
                $table->string('cover_image')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('library_book_copies')) {
            Schema::create('library_book_copies', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
                $table->string('barcode', 64);
                $table->string('accession_number', 64);
                $table->unsignedInteger('copy_number')->default(1);
                $table->string('status', 16)->default('Available'); // Available, Issued, Reserved, Lost, Damaged, Under_Repair
                $table->string('condition', 8)->default('Good');     // New, Good, Fair, Poor
                $table->date('acquisition_date')->nullable();
                $table->string('acquisition_type', 16)->default('Purchase'); // Purchase, Donation, Govt_Grant
                $table->timestamps();
                $table->unique(['school_id', 'barcode']);
                $table->unique(['school_id', 'accession_number']);
            });
        }

        if (!Schema::hasTable('library_members')) {
            Schema::create('library_members', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->string('member_type', 8); // Student, Teacher, Staff
                $table->string('membership_number', 32);
                $table->unsignedTinyInteger('max_books_allowed')->nullable(); // null = school default
                $table->unsignedSmallInteger('issue_duration_days')->nullable();
                $table->string('status', 10)->default('Active'); // Active, Suspended, Expired
                $table->date('valid_until')->nullable();
                $table->timestamps();
                $table->unique(['school_id', 'user_id']);
                $table->unique(['school_id', 'membership_number']);
            });
        }

        if (!Schema::hasTable('library_issues')) {
            Schema::create('library_issues', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('book_copy_id')->constrained('library_book_copies')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('library_members')->cascadeOnDelete();
                $table->unsignedBigInteger('issued_by')->nullable();
                $table->date('issue_date');
                $table->date('due_date')->index();
                $table->date('return_date')->nullable();
                $table->unsignedBigInteger('returned_to')->nullable();
                $table->string('status', 10)->default('Issued')->index(); // Issued, Returned, Overdue, Lost
                $table->unsignedTinyInteger('renewal_count')->default(0);
                $table->decimal('late_fine_amount', 10, 2)->default(0);
                $table->string('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('library_reservations')) {
            Schema::create('library_reservations', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('library_members')->cascadeOnDelete();
                $table->date('reservation_date');
                $table->date('expiry_date')->nullable();
                $table->string('status', 10)->default('Pending')->index(); // Pending, Ready, Fulfilled, Expired, Cancelled
                $table->unsignedBigInteger('held_copy_id')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('library_fines')) {
            Schema::create('library_fines', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('issue_id')->constrained('library_issues')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('library_members')->cascadeOnDelete();
                $table->string('reason', 16)->default('Overdue'); // Overdue, Lost, Damaged
                $table->decimal('amount', 10, 2);
                $table->unsignedSmallInteger('overdue_days')->default(0);
                $table->decimal('per_day_rate', 8, 2)->default(0);
                $table->string('status', 8)->default('Unpaid')->index(); // Unpaid, Paid, Waived
                $table->string('payment_mode', 16)->nullable(); // Cash, Online
                $table->timestamp('paid_at')->nullable();
                $table->string('waived_reason')->nullable();
                $table->unsignedBigInteger('collected_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('library_digital_access_logs')) {
            Schema::create('library_digital_access_logs', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->index();
                $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->timestamp('accessed_at');
                $table->string('ip_address', 45)->nullable();
                $table->string('device')->nullable();
            });
        }

        if (!Schema::hasTable('library_settings')) {
            Schema::create('library_settings', static function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->unique();
                $table->unsignedTinyInteger('student_max_books')->default(2);
                $table->unsignedSmallInteger('student_issue_days')->default(14);
                $table->unsignedTinyInteger('staff_max_books')->default(5);
                $table->unsignedSmallInteger('staff_issue_days')->default(30);
                $table->decimal('fine_per_day', 8, 2)->default(1);
                $table->unsignedTinyInteger('grace_period_days')->default(1);
                $table->unsignedTinyInteger('max_renewals')->default(2);
                $table->boolean('skip_holidays')->default(true);
                $table->boolean('allow_online_reservation')->default(true);
                $table->unsignedTinyInteger('reservation_hold_days')->default(3);
                $table->timestamps();
            });
        }

        AddonRegistry::syncSchoolPermissions();
    }

    public function down(): void
    {
        foreach (['library_settings', 'library_digital_access_logs', 'library_fines', 'library_reservations',
                     'library_issues', 'library_members', 'library_book_copies', 'library_books', 'library_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
