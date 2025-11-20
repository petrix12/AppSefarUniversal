<?php

namespace App\Http\Controllers;

use App\Models\WhatsappRequest;
use Illuminate\Http\Request;

class WhatsappController extends Controller
{
    public function pending()
    {
        return WhatsappRequest::where('status', 'pending')->limit(10)->get();
    }

    public function success($id)
    {
        WhatsappRequest::findOrFail($id)->update([
            'status' => 'sent',
            'error_message' => null
        ]);

        return ['status' => 'ok'];
    }

    public function fail(Request $request, $id)
    {
        WhatsappRequest::findOrFail($id)->update([
            'status' => 'error',
            'error_message' => $request->error_message
        ]);

        return ['status' => 'ok'];
    }
}
