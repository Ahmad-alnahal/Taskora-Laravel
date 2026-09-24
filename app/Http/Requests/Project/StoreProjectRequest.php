<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المشروع مطلوب',
            'hourly_rate.numeric' => 'سعر الساعة يجب أن يكون رقماً',
            'hourly_rate.min' => 'سعر الساعة يجب أن يكون رقماً موجباً',
            'client_uuid.uuid' => 'معرّف الطلب (client_uuid) يجب أن يكون UUID صحيحاً',
        ];
    }
}
