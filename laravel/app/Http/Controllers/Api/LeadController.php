<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lead = Lead::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'source' => 'chat',
            'organization_id' => $request->organization_id ?? null,
            'user_id' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'lead_id' => $lead->id]);
    }

    public function index(Request $request)
    {
        $query = Lead::query();
        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        return response()->json($query->orderBy('created_at', 'desc')->get());
    }
}
