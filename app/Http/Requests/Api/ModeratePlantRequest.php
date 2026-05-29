<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModeratePlantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_action' => [
                'required',
                Rule::in([
                    'hide_plant',
                    'warn_user',
                    'block_user',
                ]),
            ],
            'admin_comment' => 'nullable|string|max:1000',
        ];
    }
}
