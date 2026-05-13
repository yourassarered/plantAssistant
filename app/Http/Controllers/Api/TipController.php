<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTipStatusRequest;
use App\Http\Resources\TipResource;
use App\Models\Plant;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TipController extends Controller
{
    public function index(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $user = $request->user();

        abort_unless(
            $plant->is_public || ($user && ($plant->user_id === $user->id || $user->isAdmin())),
            403
        );

        $tips = Tip::where('plant_id', $plantId)
            ->with('author')
            ->orderBy('created_at', 'desc')
            ->paginate(min($request->integer('per_page', 15), 100));

        return TipResource::collection($tips);
    }

    public function store(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('create', [Tip::class, $plant->user_id, (bool) $plant->is_public]);

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

    public function show(Request $request, $id)
    {
        $tip = Tip::with('author', 'plant')->findOrFail($id);
        $this->authorize('view', $tip);

        return new TipResource($tip);
    }

    public function myTips(Request $request)
    {
        $tips = Tip::where('author_id', $request->user()->id)
            ->with(['plant', 'author'])
            ->orderBy('created_at', 'desc')
            ->paginate(min($request->integer('per_page', 15), 100));

        return TipResource::collection($tips);
    }

    public function receivedTips(Request $request)
    {
        $userId = $request->user()->id;

        $tips = Tip::whereIn('plant_id',
            Plant::where('user_id', $userId)->pluck('id')
        )
            ->with(['plant', 'author'])
            ->orderBy('created_at', 'desc')
            ->paginate(min($request->integer('per_page', 15), 100));

        return TipResource::collection($tips);
    }

    public function receivedTipsByStatus(Request $request, $status)
    {
        $validStatuses = ['pending', 'accepted', 'rejected'];

        if (! in_array($status, $validStatuses, true)) {
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
            ->paginate(min($request->integer('per_page', 15), 100));

        return TipResource::collection($tips);
    }

    public function updateStatus(UpdateTipStatusRequest $request, $id)
    {
        $tip = Tip::with(['plant', 'author'])->findOrFail($id);
        $this->authorize('updateStatus', $tip);

        $newStatus = $request->string('status')->value();

        $tip = DB::transaction(function () use ($tip, $newStatus) {
            $oldStatus = $tip->status;

            if ($newStatus === 'accepted' && $oldStatus !== 'accepted') {
                $tip->author->increment('rank');
            }

            if ($newStatus === 'rejected' && $oldStatus === 'accepted') {
                $tip->author->decrement('rank');
            }

            $tip->status = $newStatus;
            $tip->save();

            return $tip;
        });

        return new TipResource($tip->fresh('author'));
    }

    public function destroy(Request $request, $id)
    {
        $tip = Tip::with(['plant', 'author'])->findOrFail($id);
        $this->authorize('delete', $tip);

        DB::transaction(function () use ($tip): void {
            if ($tip->status === 'accepted') {
                $tip->author->decrement('rank');
            }

            $tip->delete();
        });

        return response()->json([
            'message' => 'Tip deleted successfully',
        ]);
    }

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
