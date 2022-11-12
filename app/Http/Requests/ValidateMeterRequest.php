<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class ValidateMeterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //'meter' => 'required|regex:/^[0-9]+$/',
            'meter' => 'required|between:10,15',
            'disco' => 'required',
            'amount' => 'required',
            'mode' => 'required',
            'email' => 'required',
            'phone' => 'required|regex:/^[0-11]+$/',
         ];
    }

    //Add customer message
    public function messages()
    {
        return [
            'meter.required' => 'Invalid Meter Length',
            'phone.required' => 'Invalid Phone Number valid format is 08012345678',
        ];
    }
}
