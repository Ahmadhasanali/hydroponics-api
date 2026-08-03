<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Models\Farm;
use App\Models\Reminder;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use App\Models\User;
use App\Services\ReminderRecurrenceService;
use App\Services\ReminderTargetResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ReminderTargetResolver $resolver,
        private readonly ReminderRecurrenceService $recurrence,
    ) {}

    public function index(Request $request, Farm $farm): View
    {
        $this->authorize('view', $farm);

        $visibleIds = $this->resolver->visibleReminderIds($request->user());

        $reminders = Reminder::query()
            ->where('farm_id', $farm->id)
            ->whereIn('id', $visibleIds)
            ->with('targets.targetable')
            ->orderByDesc('starts_at')
            ->get();

        return view('reminders.index', compact('farm', 'reminders'));
    }

    public function create(Request $request, Farm $farm): View
    {
        $this->authorize('view', $farm);

        $farm->load('users', 'staff');
        $eligible = [];

        foreach ($farm->users as $member) {
            if ($this->resolver->canTarget($request->user(), $farm, $member)) {
                $eligible[] = ['id' => $member::class.':'.$member->id, 'name' => $member->name];
            }
        }

        foreach ($farm->staff as $staff) {
            if ($this->resolver->canTarget($request->user(), $farm, $staff)) {
                $eligible[] = ['id' => $staff::class.':'.$staff->id, 'name' => $staff->name.' (Petugas)'];
            }
        }

        return view('reminders.create', compact('farm', 'eligible'));
    }

    public function store(StoreReminderRequest $request, Farm $farm): RedirectResponse
    {
        $validated = $request->validated();

        $targets = $this->resolver->resolveTargets(
            $request->user(),
            $farm,
            $request->targetMode(),
            $request->targetIds(),
        );

        if ($targets === []) {
            return back()->withErrors(['target_mode' => 'Tidak ada target yang valid untuk reminder ini.'])
                ->withInput();
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

        return redirect()->route('farm.reminders.index', $farm)
            ->with('success', 'Reminder berhasil dibuat.');
    }

    public function show(Request $request, Farm $farm, Reminder $reminder): View
    {
        $this->authorize('view', $reminder);

        if ($reminder->farm_id !== $farm->id) {
            abort(404);
        }

        $reminder->load(['targets.targetable', 'occurrences']);

        return view('reminders.show', compact('farm', 'reminder'));
    }

    public function edit(Request $request, Farm $farm, Reminder $reminder): View
    {
        Gate::authorize('update', $reminder);

        if ($reminder->farm_id !== $farm->id) {
            abort(404);
        }

        return view('reminders.edit', compact('farm', 'reminder'));
    }

    public function update(UpdateReminderRequest $request, Farm $farm, Reminder $reminder): RedirectResponse
    {
        Gate::authorize('update', $reminder);

        if ($reminder->farm_id !== $farm->id) {
            abort(404);
        }

        $validated = $request->validated();

        $reminder->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'starts_at' => $validated['starts_at'],
            'recurrence' => $validated['recurrence'] ?? ['type' => 'none'],
            'advance_notify_minutes' => $validated['advance_notify_minutes'] ?? null,
        ]);

        // Reset occurrence yang belum dikirim agar mengikuti jadwal baru
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

        return redirect()->route('farm.reminders.show', [$farm, $reminder])
            ->with('success', 'Reminder berhasil diperbarui.');
    }

    public function destroy(Request $request, Farm $farm, Reminder $reminder): RedirectResponse
    {
        Gate::authorize('delete', $reminder);

        if ($reminder->farm_id !== $farm->id) {
            abort(404);
        }

        $reminder->delete();

        return redirect()->route('farm.reminders.index', $farm)
            ->with('success', 'Reminder berhasil dihapus.');
    }

    public function calendar(Request $request, Farm $farm): View
    {
        $this->authorize('view', $farm);

        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $visibleIds = $this->resolver->visibleReminderIds($request->user());

        $reminders = Reminder::query()
            ->where('farm_id', $farm->id)
            ->whereIn('id', $visibleIds)
            ->get();

        // Occurrence tersimpan (sudah di-track) dalam rentang bulan
        $stored = ReminderOccurrence::query()
            ->whereHas('reminder', fn ($q) => $q->where('farm_id', $farm->id))
            ->whereIn('reminder_id', $visibleIds)
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('reminder')
            ->get();

        // Occurrence yang belum tersimpan untuk reminder recurring (dijabarkan on-demand)
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

        // Gabungkan, buang duplikat (yang sudah tersimpan), lalu group per tanggal
        $storedKeys = $stored->map(fn ($o) => $o->reminder_id.'|'.$o->scheduled_at->format('Y-m-d H:i'));

        $byDate = $stored
            ->concat($generated->filter(fn ($item) => ! $storedKeys->contains(
                $item->reminder->id.'|'.$item->scheduled_at->format('Y-m-d H:i'),
            )))
            ->groupBy(fn ($item) => $item->scheduled_at->format('Y-m-d'));

        return view('reminders.calendar', compact('farm', 'byDate', 'start', 'month'));
    }

    public function occurrenceDone(Request $request, Farm $farm, ReminderOccurrence $occurrence): RedirectResponse
    {
        if ($occurrence->reminder->farm_id !== $farm->id) {
            abort(403);
        }

        $user = $request->user();

        $canComplete = $occurrence->reminder->created_by_type === User::class
            && $occurrence->reminder->created_by_id === $user->id;

        if (! $canComplete) {
            $canComplete = $occurrence->reminder->targets()
                ->where('targetable_type', User::class)
                ->where('targetable_id', $user->id)
                ->exists();
        }

        if (! $canComplete) {
            abort(403);
        }

        $occurrence->markDone(User::class, $user->id);

        return back()->with('success', 'Reminder ditandai selesai.');
    }

    public function occurrenceSkip(Request $request, Farm $farm, ReminderOccurrence $occurrence): RedirectResponse
    {
        if ($occurrence->reminder->farm_id !== $farm->id) {
            abort(403);
        }

        $user = $request->user();

        $canSkip = $occurrence->reminder->created_by_type === User::class
            && $occurrence->reminder->created_by_id === $user->id;

        if (! $canSkip) {
            $canSkip = $occurrence->reminder->targets()
                ->where('targetable_type', User::class)
                ->where('targetable_id', $user->id)
                ->exists();
        }

        if (! $canSkip) {
            abort(403);
        }

        $occurrence->markSkipped();

        return back()->with('success', 'Reminder dilewati.');
    }
}
