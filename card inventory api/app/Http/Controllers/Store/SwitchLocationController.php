<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SwitchLocationController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate([
            'location_id' => ['nullable', 'integer'],
        ]);

        $locationId = $request->input('location_id');

        // Allow clearing selection
        if (empty($locationId)) {
            Session::forget('current_location_id');
            return redirect()->back();
        }

        $location = Location::with('store')->find($locationId);

        if (! $location) {
            return redirect()->back()->withErrors(['location_id' => 'Location not found.']);
        }

        $currentStore = $user->currentStore();

        if (! $currentStore || $location->store_id !== $currentStore->id) {
            return redirect()->back()->withErrors(['location_id' => 'Location does not belong to your current store.']);
        }

        if (! $user->canAccessLocation($location)) {
            return redirect()->back()->withErrors(['location_id' => 'You do not have access to this location.']);
        }

        Session::put('current_location_id', $location->id);

        return redirect()->back();
    }
}
