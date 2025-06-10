<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->foreignId('role_id')->after('user_id')->constrained()->onDelete('cascade');
            $table->string('employee_id')->unique()->after('role_id');
            $table->string('phone')->nullable()->after('contact_number');
            $table->date('hire_date')->nullable()->after('birth_date');
            $table->decimal('salary', 10, 2)->nullable()->after('hire_date');
            $table->boolean('is_active')->default(true)->after('is_deleted');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'role_id', 'employee_id', 'phone', 'hire_date', 
                'salary', 'is_active', 'last_login_at'
            ]);
        });
    }
};