<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'client_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'hourly_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['active', 'completed', 'archived'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'حالة المشروع غير صحيحة',
            'hourly_rate.numeric' => 'سعر الساعة يجب أن يكون رقماً',
            'hourly_rate.min' => 'سعر الساعة يجب أن يكون رقماً موجباً',
        ];
    }
}
