<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                $this->scalarRule(...),
            ],
            'to' => [
                'required',
                $this->scalarRule(...),
            ],
        ];
    }

    private function scalarRule(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value) && ! is_string($value)) {
            $fail("The {$attribute} field must be a key.");
        }
    }

    public function rowId(): int|string
    {
        return $this->scalarInput('id');
    }

    public function targetId(): int|string
    {
        return $this->scalarInput('to');
    }

    private function scalarInput(string $key): int|string
    {
        $value = $this->input($key);

        return is_int($value) ? $value : (string) $value;
    }
}
