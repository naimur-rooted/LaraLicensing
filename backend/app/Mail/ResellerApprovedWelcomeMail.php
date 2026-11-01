<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResellerApprovedWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $resellerData;
    public string $loginUrl;

    public function __construct(array $resellerData, string $loginUrl = '')
    {
        $this->resellerData = $resellerData;
        $this->loginUrl = $loginUrl ?: (env('APP_URL', 'http://127.0.0.1:8080') . '/login');
    }

    public function build()
    {
        $company = $this->resellerData['Name'] ?? $this->resellerData['CompanyName'] ?? 'Partner';

        return $this->subject("Welcome to the Partner Network! Your Reseller Account is Ready")
            ->view('emails.reseller-approved-welcome')
            ->with([
                'companyName' => $company,
                'loginEmail' => $this->resellerData['ContactEmail'] ?? '',
                'prefix' => $this->resellerData['Prefix'] ?? '',
                'temporaryPassword' => $this->resellerData['TemporaryPassword'] ?? 'Welcome123!',
                'loginUrl' => $this->loginUrl,
            ]);
    }
}
