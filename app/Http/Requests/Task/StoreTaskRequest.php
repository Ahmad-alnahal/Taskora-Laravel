<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'estimated_hours' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المهمة مطلوب',
            'priority.in' => 'الأولوية غير صحيحة',
            'estimated_hours.numeric' => 'الساعات المتوقعة يجب أن تكون رقماً',
            'client_uuid.uuid' => 'معرّف الطلب (client_uuid) يجب أن يكون UUID صحيحاً',
        ];
    }
}
