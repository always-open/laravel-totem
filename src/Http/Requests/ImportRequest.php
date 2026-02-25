<?php

namespace Studio\Totem\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Studio\Totem\Rules\JsonFile;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tasks' => ['required', 'file', new JsonFile],
            'content' => ['json'],
        ];
    }

    public function messages(): array
    {
        return [
            'tasks.required' => 'Please select a file to import',
            'tasks.file' => 'Please select a file to import',
            'content' => 'File does not contain valid json',
        ];
    }

    /**
     * Get all of the input and files for the request.
     *
     * @param  array|mixed  $keys
     * @return array
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function all($keys = null)
    {
        $content = '';

        if ($jsonFile = $this->file('tasks')) {
            $content = $jsonFile->get();
        }

        return array_merge(parent::all($keys), compact('content'));
    }

    /**
     * Get the validated data from the request.
     *
     * @return array
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function validated($key = null, $default = null)
    {
        $content = '';

        if ($jsonFile = $this->file('tasks')) {
            $content = $jsonFile->get();
        }

        return array_merge(parent::validated(), compact('content'));
    }

    /**
     * * Handle a failed validation attempt.
     *
     * @param  Validator  $validator
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
