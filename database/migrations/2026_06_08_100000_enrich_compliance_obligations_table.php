<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_obligations', function (Blueprint $table) {
            $table->string('type')->default('social')->after('description');
            $table->string('authority')->nullable()->after('type');
            $table->string('reference_period')->default('previous_month')->after('frequency');
            $table->json('due_rule')->nullable()->after('deadline_day_of_month');
            $table->unsignedTinyInteger('grace_days')->default(0)->after('due_rule');
            $table->date('effective_from')->nullable()->after('grace_days');
            $table->json('settings')->nullable()->after('effective_from');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_obligations', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'authority',
                'reference_period',
                'due_rule',
                'grace_days',
                'effective_from',
                'settings',
            ]);
        });
    }
};
