<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Список комнат текущего пользователя
     */
    public function index(Request $request)
    {
        $rooms = Room::where('user_id', $request->user()->id)
            ->with('plants')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return RoomResource::collection($rooms);
    }

    /**
     * Создание комнаты
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $room = Room::create([
            'name' => $validated['name'],
            'user_id' => $request->user()->id,
        ]);

        return new RoomResource($room);
    }

    /**
     * Просмотр комнаты с растениями
     */
    public function show(Request $request, $id)
    {
        $room = Room::where('user_id', $request->user()->id)
            ->with('plants')
            ->findOrFail($id);

        return new RoomResource($room);
    }

    /**
     * Редактирование комнаты
     */
    public function update(Request $request, $id)
    {
        $room = Room::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $room->update($validated);

        return new RoomResource($room);
    }

    /**
     * Удаление комнаты
     */
    public function destroy(Request $request, $id)
    {
        $room = Room::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $room->plants()->update(['room_id' => null]);

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }
}