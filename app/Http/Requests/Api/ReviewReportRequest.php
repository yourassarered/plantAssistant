<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
            'admin_comment' => 'nullable|string|max:1000',
            'resolution_action' => [
                'nullable',
                Rule::in([
                    'tip_delete_rank',
                    'block_user',
                    'tip_warn_rank',
                    'hide_plant',
                    'warn_user',
                ]),
            ],
        ];
    }
}
