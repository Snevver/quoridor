<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MakeMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('game')->hasPlayer($this->user());
    }

    public function rules(): array
    {
        return [
            'move_type' => ['required', 'in:pawn,wall'],
            'to' => ['required_if:move_type,pawn', 'array', 'size:2'],
            'to.*' => ['integer', 'min:0', 'max:8'],
            'x' => ['required_if:move_type,wall', 'integer', 'min:0', 'max:7'],
            'y' => ['required_if:move_type,wall', 'integer', 'min:0', 'max:7'],
            'orientation' => ['required_if:move_type,wall', 'in:H,V'],
        ];
    }
}
