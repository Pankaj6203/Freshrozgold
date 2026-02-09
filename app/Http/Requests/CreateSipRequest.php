<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateSipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'          => 'required|numeric|min:100|max:50000',
            'frequency'       => 'required|in:daily,weekly,monthly',
            'duration_months' => 'required|integer|min:6|max:36',
            'start_date'      => 'required|date_format:Y-m-d|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min'            => 'Minimum SIP amount is ₹100',
            'start_date.after_or_equal' => 'SIP start date must be today or a future date',
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
