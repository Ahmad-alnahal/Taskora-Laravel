<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'in_review', 'completed'])],
            'estimated_hours' => ['sometimes', 'numeric', 'min:0'],
            'actual_hours' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'priority.in' => 'الأولوية غير صحيحة',
            'status.in' => 'حالة المهمة غير صحيحة',
            'estimated_hours.numeric' => 'الساعات المتوقعة يجب أن تكون رقماً',
            'actual_hours.numeric' => 'الساعات الفعلية يجب أن تكون رقماً',
        ];
    }
}
