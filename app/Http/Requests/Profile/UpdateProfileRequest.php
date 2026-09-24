<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'hourly_rate' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'الاسم يجب أن يكون 3 أحرف على الأقل',
            'hourly_rate.numeric' => 'السعر بالساعة يجب أن يكون رقماً',
            'hourly_rate.min' => 'السعر بالساعة يجب أن يكون رقماً موجباً',
        ];
    }
}
