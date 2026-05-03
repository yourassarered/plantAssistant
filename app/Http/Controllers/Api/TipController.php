<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TipResource;
use App\Models\Tip;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipController extends Controller
{
    /**
     * Все советы для конкретного растения
     */
    public function index(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);

        // Может просмотреть советы только для своих растений или публичных
        if ($plant->user_id !== $request->user()->id && !$plant->is_public) {
            abort(403, 'Unauthorized');
        }

        $tips = Tip::where('plant_id', $plantId)
            ->with('author')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return TipResource::collection($tips);
    }

    /**
     * Создание совета для растения
     */
    public function store(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);

        // Можно оставить совет только для публичных растений
        if (!$plant->is_public) {
            return response()->json([
                'message' => 'Can only leave tips for public plants',
            ], 403);
        }

        // Нельзя оставить совет для своего же растения
        if ($plant->user_id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot leave tips for your own plants',
            ], 422);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $tip = Tip::create([
            'plant_id' => $plantId,
            'author_id' => $request->user()->id,
            'content' => $validated['content'],
            'status' => 'pending',
        ]);

        return new TipResource($tip->load('author'));
    }

    /**
     * Просмотр конкретного совета
     */
    public function show(Request $request, $id)
    {
        $tip = Tip::with('author', 'plant')->findOrFail($id);

        // Проверяем доступ
        $plant = $tip->plant;
        if ($plant->user_id !== $request->user()->id && !$plant->is_public) {
            abort(403, 'Unauthorized');
        }

        return new TipResource($tip);
    }

    /**
     * Советы, созданные текущим пользователем
     */
    public function myTips(Request $request)
    {
        $tips = Tip::where('author_id', $request->user()->id)
            ->with(['plant', 'author'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return TipResource::collection($tips);
    }

    /**
     * Советы, полученные для растений текущего пользователя
     */
    public function receivedTips(Request $request)
    {
        $userId = $request->user()->id;

        $tips = Tip::whereIn('plant_id', 
            Plant::where('user_id', $userId)->pluck('id')
        )
            ->with(['plant', 'author'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return TipResource::collection($tips);
    }

    /**
     * Фильтрация полученных советов по статусу
     */
    public function receivedTipsByStatus(Request $request, $status)
    {
        $validStatuses = ['pending', 'accepted', 'rejected'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'message' => 'Invalid status',
            ], 422);
        }

        $userId = $request->user()->id;

        $tips = Tip::whereIn('plant_id',
            Plant::where('user_id', $userId)->pluck('id')
        )
            ->where('status', $status)
            ->with(['plant', 'author'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return TipResource::collection($tips);
    }

    /**
     * Изменение статуса совета (принять/отклонить)
     */
    public function updateStatus(Request $request, $id)
    {
        $tip = Tip::with('plant')->findOrFail($id);

        // Может менять статус только владелец растения
        if ($tip->plant->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
        ]);

        $oldStatus = $tip->status;
        $newStatus = $validated['status'];

        // Если совет принят и это первый раз (был pending), увеличиваем ранг автора
        if ($newStatus === 'accepted' && $oldStatus !== 'accepted') {
            $author = $tip->author;
            $author->increment('rank');
        }

        // Если совет был принят, но теперь его отклоняют, уменьшаем ранг
        if ($newStatus === 'rejected' && $oldStatus === 'accepted') {
            $author = $tip->author;
            $author->decrement('rank');
        }

        $tip->status = $newStatus;
        $tip->save();

        return new TipResource($tip->load('author'));
    }

    /**
     * Удаление совета (может удалить автор или владелец растения)
     */
    public function destroy(Request $request, $id)
    {
        $tip = Tip::with('plant')->findOrFail($id);

        // Может удалить только автор совета или владелец растения
        if ($tip->author_id !== $request->user()->id && $tip->plant->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Если совет был принят, уменьшаем ранг автора
        if ($tip->status === 'accepted') {
            $author = $tip->author;
            $author->decrement('rank');
        }

        $tip->delete();

        return response()->json([
            'message' => 'Tip deleted successfully',
        ]);
    }

    /**
     * Количество советов по статусам для текущего пользователя
     */
    public function tipStats(Request $request)
    {
        $userId = $request->user()->id;

        $stats = [
            'pending' => Tip::whereIn('plant_id',
                Plant::where('user_id', $userId)->pluck('id')
            )->where('status', 'pending')->count(),
            'accepted' => Tip::whereIn('plant_id',
                Plant::where('user_id', $userId)->pluck('id')
            )->where('status', 'accepted')->count(),
            'rejected' => Tip::whereIn('plant_id',
                Plant::where('user_id', $userId)->pluck('id')
            )->where('status', 'rejected')->count(),
        ];

        $stats['total'] = array_sum($stats);

        return response()->json($stats);
    }
}