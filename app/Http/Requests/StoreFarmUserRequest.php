<?php

namespace App\Http\Requests;

use App\Models\Farm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFarmUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Farm $farm */
        $farm = $this->route('farm');

        return $farm->users()
            ->where('user_id', $this->user()->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'exists:users,name'],
        ];
    }
}
