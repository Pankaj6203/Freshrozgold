<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateFdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uniqueId'   => 'required|string',
            'goldWeight'  => 'required|numeric|min:0.5',
            'tenure'      => 'required|integer|min:1|max:60',
            'schemeId'    => 'required|string',
            'blockId'     => 'required|string',
            'lockPrice'   => 'required|numeric|min:0',
            'metalType'   => 'required|in:gold,silver',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
