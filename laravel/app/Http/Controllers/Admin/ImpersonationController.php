<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start($userId)
    {
        $admin = Auth::user();
        if (!$admin || $admin->role !== 'admin') abort(403);

        $user = User::findOrFail($userId);
        session(['impersonator_id' => $admin->id]);
        Auth::login($user);
        Log::info('Admin started impersonation', ['admin_id' => $admin->id, 'user_id' => $user->id]);
        return redirect()->route($user->role === 'customer' ? 'customer.dashboard' : 'home');
    }

    public function stop()
    {
        $impersonatorId = session('impersonator_id');
        if (!$impersonatorId) return redirect()->route('admin.dashboard');
        $admin = User::find($impersonatorId);
        if ($admin) {
            session()->forget('impersonator_id');
            \Auth::login($admin);
            Log::info('Admin stopped impersonation', ['admin_id' => $admin->id]);
            return redirect()->route('admin.dashboard');
        }
        return redirect('/');
    }
}
