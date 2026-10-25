<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public developer self-serve registration payload validator.
 */
final class DeveloperRegisterRequest extends FormRequest
{
    private const EMAIL_MAX = 254;
    private const PASSWORD_MIN = 12;
    private const PASSWORD_MAX = 128;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'Email' => ['required', 'email:rfc', 'max:' . self::EMAIL_MAX],
            'Password' => ['required', 'string', 'min:' . self::PASSWORD_MIN, 'max:' . self::PASSWORD_MAX],
            'DisplayName' => ['nullable', 'string', 'max:100'],
            'CompanyName' => ['nullable', 'string', 'max:150'],
            'SelectedPlan' => ['required', 'string', 'in:STARTER,ESSENTIAL,PRO,MAX'],
            'BillingInterval' => ['nullable', 'string', 'in:monthly,annual,biennial'],
        ];
    }
}
