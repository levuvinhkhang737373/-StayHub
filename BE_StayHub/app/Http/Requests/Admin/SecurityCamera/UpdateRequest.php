<?php

namespace App\Http\Requests\Admin\SecurityCamera;

class UpdateRequest extends StoreRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['building_id'][0] = 'sometimes';
        $rules['name'][0] = 'sometimes';
        $rules['source_type'][0] = 'sometimes';
        $rules['stream_url'][0] = 'sometimes';
        $rules['password'] = ['nullable', 'string', 'max:255'];

        return $rules;
    }
}
