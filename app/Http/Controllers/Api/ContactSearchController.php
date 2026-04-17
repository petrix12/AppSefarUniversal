<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ContactSearchController extends Controller
{
    public function search(Request $request)
    {
        $term    = $request->input('q', '');
        $perPage = 20;
        $page    = (int) $request->input('page', 1);

        $query = User::role('Cliente')
            ->where(function ($q) use ($term) {
                $q->where('name',  'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'email'], 'page', $page);

        return response()->json([
            'results' => $query->map(fn($u) => [
                'id'   => $u->id,
                'text' => "{$u->name} — {$u->email}",
            ]),
            'pagination' => [
                'more' => $query->hasMorePages(),
            ],
        ]);
    }
}
