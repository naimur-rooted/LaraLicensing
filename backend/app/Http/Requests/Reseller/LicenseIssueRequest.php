<?php

declare(strict_types=1);

namespace App\Http\Requests\Reseller;

use App\Exceptions\ValidationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body validation for reseller license issue/store requests.
 */
final class LicenseIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('PrefixValue') === false && $this->has('prefixValue') === true) {
            $merge['PrefixValue'] = $this->input('prefixValue');
        }
        if ($this->has('TierName') === false && $this->has('tierName') === true) {
            $merge['TierName'] = $this->input('tierName');
        }
        if ($this->has('EnvironmentName') === false && $this->has('environmentName') === true) {
            $merge['EnvironmentName'] = $this->input('environmentName');
        }
        if ($this->has('LicenseCategory') === false && $this->has('licenseCategory') === true) {
            $merge['LicenseCategory'] = $this->input('licenseCategory');
        }
        if ($this->has('ProductSlug') === false && $this->has('productSlug') === true) {
            $merge['ProductSlug'] = $this->input('productSlug');
        }
        if ($this->has('TierName') === false && isset($merge['TierName']) === false) {
            $merge['TierName'] = 'Tier1';
        }
        if ($this->has('EnvironmentName') === false && isset($merge['EnvironmentName']) === false) {
            $merge['EnvironmentName'] = 'Production';
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'PrefixValue' => ['required', 'string', 'max:32'],
            'TierName' => ['required', 'string'],
            'EnvironmentName' => ['required', 'string'],
            'LicenseCategory' => ['nullable', 'string'],
            'ProductSlug' => ['nullable', 'string', 'max:64'],
            'ExpiresAt' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            foreach ((array) $messages as $msg) {
                $errors[] = ['Field' => (string) $field, 'Rule' => (string) $msg];
            }
        }
        throw ValidationException::validationFailed(
            'License payload failed validation.',
            $errors,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->validated();
    }
}
