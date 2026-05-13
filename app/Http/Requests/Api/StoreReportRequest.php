<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(['inappropriate_image', 'spam', 'abuse', 'misinformation', 'other'])],
            'details' => 'nullable|string|max:1000',
        ];
    }
}
