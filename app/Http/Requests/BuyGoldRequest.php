<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BuyGoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchantTransactionId' => 'nullable|string',
            'metalType'             => 'required|string|in:gold,silver',
            'lockPrice'             => 'nullable|numeric|min:0',
            'blockId'               => 'nullable|string',
            'uniqueId'              => 'nullable|string',
            'quantity'              => 'required_without:amount|nullable|numeric|min:0.0001',
            'amount'                => 'required_without:quantity|nullable|numeric|min:1',
            'modeOfPayment'         => 'nullable|string',
            'referenceType'         => 'nullable|string',
            'referenceId'           => 'nullable|string',
            'utmSource'             => 'nullable|string',
            'utmMedium'             => 'nullable|string',
            'utmCampaign'           => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'metalType.required'             => 'Metal type (gold/silver) is required',
            'quantity.required_without'      => 'Either quantity or amount is required',
            'amount.required_without'        => 'Either quantity or amount is required',
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
