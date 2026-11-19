<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_superadmin')->default(false)->index());
        Schema::table('client_notes', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
        // Explicitly authorized initial account; never grant on a future signup.
        $users = User::where('email', 'sistemasccs@sefarvzla.com')->get();
        if ($users->count() === 1 && ! $users->first()->hasRole('Cliente')) {
            $users->first()->forceFill(['is_superadmin' => true])->save();
        }
    }

    public function down(): void
    {
        Schema::table('client_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_superadmin']);
            $table->dropColumn('is_superadmin');
        });
    }
};
