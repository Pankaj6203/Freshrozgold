<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateAugmontUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uniqueId'        => 'required|string|min:3|max:30|regex:/^[a-zA-Z0-9_-]+$/',
            'userName'        => 'required|string|min:2|max:50',
            'mobileNumber'    => 'nullable|digits:10',
            'emailId'         => 'nullable|email|max:50',
            'userCity'        => 'nullable|string',
            'userState'       => 'nullable|string',
            'userPincode'     => 'nullable|digits:6',
            'dateOfBirth'     => 'nullable|date_format:Y-m-d',
            'nomineeName'     => 'nullable|string|max:50',
            'nomineeDateOfBirth' => 'nullable|date_format:Y-m-d|before:-18 years',
            'nomineeRelation' => 'nullable|string|min:2|max:25',
            'utmSource'       => 'nullable|string',
            'utmMedium'       => 'nullable|required_with:utmSource|string',
            'utmCampaign'     => 'nullable|required_with:utmSource|string',
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
