<?php

namespace App\Http\Requests;

use App\Models\ClientNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ! $this->user()->hasRole('Cliente');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $this->merge(['note' => trim($this->input('note'))]);
        }
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:' . ClientNote::MAX_LENGTH]];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Escribe el contenido de la nota.',
            'note.string' => 'La nota debe ser un texto.',
            'note.max' => 'La nota no puede superar los ' . ClientNote::MAX_LENGTH . ' caracteres.',
        ];
    }
}
