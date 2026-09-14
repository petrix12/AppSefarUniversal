<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $requiredTables = [
            'document_requests', 'files', 'genealogy_document_person',
            'genealogy_document_union', 'genealogy_unions',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $now = now();
        $requests = DB::table('document_requests')
            ->where('document_type', 'genealogico')
            ->whereNotNull('person_id')
            ->whereIn('status', ['resuelto', 'aprobada'])
            ->get(['id', 'person_id', 'genealogy_union_id']);

        foreach ($requests as $request) {
            $files = DB::table('files')
                ->where('document_request_id', $request->id)
                ->get(['id']);

            foreach ($files as $file) {
                DB::table('genealogy_document_person')->insertOrIgnore([
                    'file_id' => $file->id,
                    'person_id' => $request->person_id,
                    'relationship' => 'subject',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (Schema::hasColumn('files', 'IDPersonaNew')) {
                    DB::table('files')->where('id', $file->id)->update(['IDPersonaNew' => $request->person_id]);
                }

                if (! $request->genealogy_union_id) {
                    continue;
                }

                DB::table('genealogy_document_union')->insertOrIgnore([
                    'file_id' => $file->id,
                    'genealogy_union_id' => $request->genealogy_union_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $union = DB::table('genealogy_unions')->find($request->genealogy_union_id);
                foreach ([$union?->spouse_one_id, $union?->spouse_two_id] as $personId) {
                    if ($personId) {
                        DB::table('genealogy_document_person')->insertOrIgnore([
                            'file_id' => $file->id,
                            'person_id' => $personId,
                            'relationship' => 'spouse',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // The associations describe real customer documents; rollback must not
        // erase them.
    }
};
