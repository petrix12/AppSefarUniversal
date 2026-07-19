<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProfileJetController extends Controller
{
    /**
     * Show the user profile screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->isCliente()) {
            return view('profile.show-client', [
                'request' => $request,
                'user' => $user,
            ]);
        }

        if ($user->canViewSalesProfile()) {
            return view('profile.show-ventas', [
                'request' => $request,
                'user' => $user,
            ]);
        }

        return view('profile.show', [
            'request' => $request,
            'user' => $user,
        ]);
    }
}
