<?php

namespace App\Http\Controllers;

use App\Services\HuggingFaceAssistantService;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    protected $assistantService;

    public function __construct(HuggingFaceAssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    public function chat(Request $request)
    {
        $message = $request->input('message');
        $response = $this->assistantService->sendMessage($message);

        return response()->json($response);
    }
}
