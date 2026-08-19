<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Services\ReminderRecurrenceService;
use App\Services\ReminderTargetResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ReminderTargetResolver $resolver,
        private readonly ReminderRecurrenceService $recurrence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $visibleIds = $this->resolver->visibleReminderIds($request->user());

        $query = Reminder::query()
            ->whereIn('id', $visibleIds)
            ->with('targets.targetable', 'occurrences')
            ->with('farm:id,name')
            ->orderByDesc('starts_at');

        $farmId = $request->integer('farm_id');
        if ($farmId) {
            $farm = Farm::findOrFail($farmId);
            $this->authorize('view', $farm);
            $query->where('farm_id', $farmId);
        }

        $reminders = $query->paginate(30);

        return $this->paginatedResponse($reminders, 'Daftar reminder.');
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $farm = Farm::findOrFail($validated['farm_id']);

        $targets = $this->resolver->resolveTargets(
            $request->user(),
            $farm,
            $request->targetMode(),
            $request->targetIds(),
        );

        if ($targets === []) {
            return $this->errorResponse('Tidak ada target yang valid untuk reminder ini.', 422);
        }

        $recurrence = $request->recurrence() ?? ['type' => 'none'];

        $reminder = DB::transaction(function () use ($request, $farm, $validated, $targets, $recurrence) {
            $reminder = Reminder::query()->create([
                'farm_id' => $farm->id,
                'created_by_type' => $request->user()::class,
                'created_by_id' => $request->user()->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'starts_at' => $validated['starts_at'],
                'recurrence' => $recurrence,
                'advance_notify_minutes' => $validated['advance_notify_minutes'] ?? null,
            ]);

            foreach ($targets as $target) {
                ReminderTarget::query()->create([
                    'reminder_id' => $reminder->id,
                    'targetable_type' => $target['type'],
                    'targetable_id' => $target['id'],
                ]);
            }

            $startsAt = Carbon::parse($validated['starts_at']);

            ReminderOccurrence::query()->create([
                'reminder_id' => $reminder->id,
                'scheduled_at' => $startsAt,
                'advance_notify_at' => isset($validated['advance_notify_minutes'])
                    ? $startsAt->copy()->subMinutes($validated['advance_notify_minutes'])
                    : null,
            ]);

            return $reminder;
        });

        return $this->successResponse(
            ['reminder' => $reminder->load('targets.targetable', 'occurrences')],
            'Reminder berhasil dibuat.',
            201,
        );
    }

    public function show(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorize('view', $reminder);

        $reminder->load(['targets.targetable', 'occurrences']);

        return $this->successResponse(['reminder' => $reminder]);
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): JsonResponse
    {
        Gate::authorize('update', $reminder);

        $validated = $request->validated();

        $reminder->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'starts_at' => $validated['starts_at'],
            'recurrence' => $validated['recurrence'] ?? ['type' => 'none'],
            'advance_notify_minutes' => $validated['advance_notify_minutes'] ?? null,
        ]);

        $reminder->occurrences()
            ->whereNull('notified_at')
            ->whereNull('advance_notified_at')
            ->delete();

        $startsAt = Carbon::parse($validated['starts_at']);

        ReminderOccurrence::query()->create([
            'reminder_id' => $reminder->id,
            'scheduled_at' => $startsAt,
            'advance_notify_at' => isset($validated['advance_notify_minutes'])
                ? $startsAt->copy()->subMinutes($validated['advance_notify_minutes'])
                : null,
        ]);

        return $this->successResponse(
            ['reminder' => $reminder->refresh()->load('targets.targetable', 'occurrences')],
            'Reminder berhasil diperbarui.',
        );
    }

    public function destroy(Request $request, Reminder $reminder): Response
    {
        Gate::authorize('delete', $reminder);

        $reminder->delete();

        return response()->noContent();
    }
}
