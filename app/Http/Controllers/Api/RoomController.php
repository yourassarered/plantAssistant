<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Room::class);

        $rooms = Room::where('user_id', $request->user()->id)
            ->with('plants')
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 15), 100));

        return RoomResource::collection($rooms);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Room::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $room = Room::create([
            'name' => $validated['name'],
            'user_id' => $request->user()->id,
        ]);

        return new RoomResource($room);
    }

    public function show(Request $request, $id)
    {
        $room = Room::with('plants')->findOrFail($id);
        $this->authorize('view', $room);

        return new RoomResource($room);
    }

    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);
        $this->authorize('update', $room);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $room->update($validated);

        return new RoomResource($room);
    }

    public function destroy(Request $request, $id)
    {
        $room = Room::findOrFail($id);
        $this->authorize('delete', $room);

        $room->plants()->update(['room_id' => null]);
        $room->delete();

        return response()->json([
            'message' => 'Комната удалена',
        ]);
    }
}
