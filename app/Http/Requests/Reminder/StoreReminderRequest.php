<?php

namespace App\Http\Requests\Reminder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $farmId = $this->input('farm_id');

        if (! $farmId || ! $this->user()->farms()->whereKey($farmId)->exists()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'farm_id' => ['required', 'integer', 'exists:farms,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'starts_at' => ['required', 'date', 'after:now'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.type' => ['required_with:recurrence', Rule::in(['none', 'interval', 'weekly', 'monthly'])],
            'recurrence.every_days' => ['required_if:recurrence.type,interval', 'integer', 'min:1'],
            'recurrence.days_of_week' => ['required_if:recurrence.type,weekly', 'array'],
            'recurrence.days_of_week.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'recurrence.days_of_month' => ['required_if:recurrence.type,monthly', 'array'],
            'recurrence.days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'advance_notify_minutes' => ['nullable', 'integer', 'min:1'],
            'target_mode' => ['required', Rule::in(['self', 'all', 'specific'])],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['string', 'regex:/^(App\\\\Models\\\\(User|Farm\\\\Staff)):\\d+$/'],
        ];
    }

    public function targetMode(): string
    {
        return $this->validated('target_mode');
    }

    /**
     * @return list<string>
     */
    public function targetIds(): array
    {
        return $this->validated('target_ids', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recurrence(): ?array
    {
        return $this->validated('recurrence');
    }
}
