<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonitorLabelController extends Controller
{
    public function store(Request $request, Monitor $monitor): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:50'],
        ]);

        $name = Str::squish($validated['label']);

        if ($name === '') {
            throw ValidationException::withMessages([
                'label' => 'Enter a label name.',
            ]);
        }

        $label = Label::firstOrCreate(
            ['normalized_name' => Str::lower($name)],
            ['name' => $name],
        );

        $monitor->labels()->syncWithoutDetaching([$label->getKey()]);

        return response()->json([
            'status' => true,
            'label' => [
                'id' => $label->getKey(),
                'name' => $label->name,
                'color' => $label->color(),
            ],
        ], 201);
    }

    public function destroy(Monitor $monitor, Label $label): JsonResponse
    {
        $monitor->labels()->detach($label);

        if (! $label->monitors()->exists()) {
            $label->delete();
        }

        return response()->json(['status' => true]);
    }
}
