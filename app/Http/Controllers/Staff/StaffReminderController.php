<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Farm\Staff;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Services\ReminderRecurrenceService;
use App\Services\ReminderTargetResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StaffReminderController extends Controller
{
    public function __construct(
        private readonly ReminderTargetResolver $resolver,
        private readonly ReminderRecurrenceService $recurrence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $staff = $request->user();
        $visibleIds = $this->resolver->visibleReminderIds($staff);

        $reminders = Reminder::query()
            ->where('farm_id', $staff->farm_id)
            ->whereIn('id', $visibleIds)
            ->with('targets.targetable')
            ->orderByDesc('starts_at')
            ->paginate(20);

        return $this->paginatedResponse($reminders);
    }

    public function store(Request $request): JsonResponse
    {
        $staff = $request->user();
        $farm = $staff->farm;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'starts_at' => ['required', 'date', 'after:now'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.type' => ['required_with:recurrence', 'in:none,interval,weekly,monthly'],
            'recurrence.every_days' => ['required_if:recurrence.type,interval', 'integer', 'min:1'],
            'recurrence.days_of_week' => ['required_if:recurrence.type,weekly', 'array'],
            'recurrence.days_of_week.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'recurrence.days_of_month' => ['required_if:recurrence.type,monthly', 'array'],
            'recurrence.days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'advance_notify_minutes' => ['nullable', 'integer', 'min:1'],
            'target_mode' => ['required', 'in:self,all,specific'],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['string', 'regex:/^(App\\\\Models\\\\(User|Farm\\\\Staff)):\\d+$/'],
        ]);

        $targets = $this->resolver->resolveTargets(
            $staff,
            $farm,
            $validated['target_mode'],
            $validated['target_ids'] ?? [],
        );

        if ($targets === []) {
            return $this->errorResponse('Tidak ada target yang valid.', 422, [
                'target_mode' => ['Tidak ada target yang valid.'],
            ]);
        }

        $reminder = DB::transaction(function () use ($request, $farm, $validated, $targets) {
            $reminder = Reminder::query()->create([
                'farm_id' => $farm->id,
                'created_by_type' => Staff::class,
                'created_by_id' => $request->user()->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'starts_at' => $validated['starts_at'],
                'recurrence' => $validated['recurrence'] ?? ['type' => 'none'],
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
            ['reminder' => $reminder->load('targets.targetable')],
            'Reminder berhasil dibuat.',
            201,
        );
    }

    public function destroy(Request $request, Reminder $reminder): JsonResponse
    {
        $staff = $request->user();

        if ($reminder->farm_id !== $staff->farm_id
            || $reminder->created_by_type !== Staff::class
            || $reminder->created_by_id !== $staff->id) {
            return $this->errorResponse('Reminder tidak ditemukan.', 403);
        }

        $reminder->delete();

        return $this->successResponse(null, 'Reminder berhasil dihapus.');
    }

    public function calendar(Request $request): JsonResponse
    {
        $staff = $request->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $visibleIds = $this->resolver->visibleReminderIds($staff);

        $reminders = Reminder::query()
            ->where('farm_id', $staff->farm_id)
            ->whereIn('id', $visibleIds)
            ->get();

        $stored = ReminderOccurrence::query()
            ->whereIn('reminder_id', $visibleIds)
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('reminder')
            ->get();

        $generated = collect();

        foreach ($reminders as $reminder) {
            $generated = $generated->concat(
                collect($this->recurrence->generateOccurrences($reminder, $start, $end))
                    ->map(fn (Carbon $c) => (object) [
                        'scheduled_at' => $c,
                        'reminder' => $reminder,
                    ]),
            );
        }

        $storedKeys = $stored->map(fn ($o) => $o->reminder_id.'|'.$o->scheduled_at->format('Y-m-d H:i'));

        $byDate = $stored
            ->concat($generated->filter(fn ($item) => ! $storedKeys->contains(
                $item->reminder->id.'|'.$item->scheduled_at->format('Y-m-d H:i'),
            )))
            ->groupBy(fn ($item) => $item->scheduled_at->format('Y-m-d'));

        return $this->successResponse([
            'month' => $month,
            'by_date' => $byDate->map(fn ($items) => $items->values()),
        ]);
    }

    public function occurrenceDone(Request $request, ReminderOccurrence $occurrence): JsonResponse
    {
        $staff = $request->user();

        if ($occurrence->reminder->farm_id !== $staff->farm_id) {
            return $this->errorResponse('Reminder tidak ditemukan.', 403);
        }

        $canComplete = $occurrence->reminder->created_by_type === Staff::class
            && $occurrence->reminder->created_by_id === $staff->id;

        if (! $canComplete) {
            $canComplete = $occurrence->reminder->targets()
                ->where('targetable_type', Staff::class)
                ->where('targetable_id', $staff->id)
                ->exists();
        }

        if (! $canComplete) {
            return $this->errorResponse('Reminder tidak ditemukan.', 403);
        }

        $occurrence->markDone(Staff::class, $staff->id);

        return $this->successResponse(null, 'Reminder ditandai selesai.');
    }

    public function occurrenceSkip(Request $request, ReminderOccurrence $occurrence): JsonResponse
    {
        $staff = $request->user();

        if ($occurrence->reminder->farm_id !== $staff->farm_id) {
            return $this->errorResponse('Reminder tidak ditemukan.', 403);
        }

        $canSkip = $occurrence->reminder->created_by_type === Staff::class
            && $occurrence->reminder->created_by_id === $staff->id;

        if (! $canSkip) {
            $canSkip = $occurrence->reminder->targets()
                ->where('targetable_type', Staff::class)
                ->where('targetable_id', $staff->id)
                ->exists();
        }

        if (! $canSkip) {
            return $this->errorResponse('Reminder tidak ditemukan.', 403);
        }

        $occurrence->markSkipped();

        return $this->successResponse(null, 'Reminder dilewati.');
    }
}
