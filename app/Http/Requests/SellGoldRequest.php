<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SellGoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchantTransactionId' => 'required|string',
            'quantity'              => 'required|numeric|min:0.0001',
            'metalType'             => 'required|string|in:gold,silver',
            'lockPrice'             => 'nullable|numeric|min:0',
            'blockId'               => 'nullable|string',
            'uniqueId'              => 'nullable|string',
            'userBank'              => 'nullable|array',
            'userBankId'            => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'merchantTransactionId.required' => 'Merchant transaction ID is required',
            'quantity.required'              => 'Quantity is required for sell transactions',
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
