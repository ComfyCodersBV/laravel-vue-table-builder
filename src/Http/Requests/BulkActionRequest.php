<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
        ];
    }
}
