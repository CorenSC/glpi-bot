<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HumanSuggestionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'observation' => ['nullable', 'string', 'max:2000'],
            'reason_code' => ['nullable', 'string', 'max:80'],
            'technician_id' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
