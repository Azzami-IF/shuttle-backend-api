<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = Vehicle::query();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('license_plate', 'like', "%{$search}%");
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'license_plate' => 'required|string|unique:vehicles',
            'capacity' => 'required|integer|min:1',
        ]);

        $vehicle = Vehicle::create($request->all());
        return response()->json($vehicle, 201);
    }

    public function show(Vehicle $vehicle)
    {
        return response()->json($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $request->validate([
            'name' => 'sometimes|string',
            'license_plate' => 'sometimes|string|unique:vehicles,license_plate,' . $vehicle->id,
            'capacity' => 'sometimes|integer|min:1',
        ]);

        $vehicle->update($request->all());
        return response()->json($vehicle);
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return response()->json(null, 204);
    }
}
