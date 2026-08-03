<?php

namespace App\Http\Requests\Reminder;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Reminder $reminder */
        $reminder = $this->route('reminder');

        return $reminder->created_by_type === User::class
            && $reminder->created_by_id === $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.type' => ['required_with:recurrence', Rule::in(['none', 'interval', 'weekly', 'monthly'])],
            'recurrence.every_days' => ['required_if:recurrence.type,interval', 'integer', 'min:1'],
            'recurrence.days_of_week' => ['required_if:recurrence.type,weekly', 'array'],
            'recurrence.days_of_week.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'recurrence.days_of_month' => ['required_if:recurrence.type,monthly', 'array'],
            'recurrence.days_of_month.*' => ['integer', 'min:1', 'max:31'],
            'advance_notify_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
