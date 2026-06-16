<?php

namespace App\Http\Controllers;

use App\Models\RouteTemplate;
use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class RouteTemplateController extends Controller
{
    public function index()
    {
        $templates = RouteTemplate::with(['vehicle', 'driver'])->orderBy('origin')->get();
        $vehicles  = Vehicle::all();
        $drivers   = User::where('role', 'driver')->get();
        return view('admin.route-templates.index', compact('templates', 'vehicles', 'drivers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicle_id'          => 'required|exists:vehicles,id',
            'driver_id'           => 'required|exists:users,id',
            'origin'              => 'required|string',
            'destination'         => 'required|string',
            'departure_time'      => 'required',
            'price'               => 'required|numeric|min:0',
            'active_days'         => 'required|array',
            'generate_days_ahead' => 'required|integer|min:1|max:90',
        ]);

        RouteTemplate::create([
            'vehicle_id'          => $request->vehicle_id,
            'driver_id'           => $request->driver_id,
            'origin'              => $request->origin,
            'destination'         => $request->destination,
            'departure_time'      => $request->departure_time,
            'price'               => $request->price,
            'active_days'         => $request->active_days,
            'generate_days_ahead' => $request->generate_days_ahead,
            'is_active'           => true,
        ]);

        // Otomatis generate jadwal segera setelah template dibuat
        Artisan::call('schedules:generate', ['--days' => $request->generate_days_ahead]);

        return redirect()->route('admin.route-templates.index')
            ->with('success', 'Template rute berhasil ditambahkan dan jadwal otomatis di-generate!');
    }

    public function update(Request $request, RouteTemplate $routeTemplate)
    {
        $routeTemplate->update([
            'is_active' => !$routeTemplate->is_active,
        ]);
        return back()->with('success', 'Status template diperbarui.');
    }

    public function destroy(RouteTemplate $routeTemplate)
    {
        $routeTemplate->delete();
        return back()->with('success', 'Template rute dihapus.');
    }

    public function generate()
    {
        Artisan::call('schedules:generate');
        $output = Artisan::output();
        return back()->with('success', 'Generate jadwal selesai! ' . strip_tags($output));
    }
}
