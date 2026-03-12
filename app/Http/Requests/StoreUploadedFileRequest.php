<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUploadedFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_type' => 'required|string|in:txt,csv,xml,xlsx',
            // Max 10MB, restrict to known tabular types, and require a real file
            'file'      => 'required|file|mimes:csv,txt,xml,xlsx|max:10240',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->hasFile('file')) {
                return;
            }

            $selectedType = strtolower((string) $this->input('file_type'));
            $uploadedFile = $this->file('file');
            $extension = strtolower((string) ($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension()));

            if ($selectedType && $extension && $selectedType !== $extension) {
                $validator->errors()->add(
                    'file',
                    'The selected file must be of type ' . strtoupper($selectedType) . " (you uploaded: .$extension)."
                );
            }
        });
    }
}
