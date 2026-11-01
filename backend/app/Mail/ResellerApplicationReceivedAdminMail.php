<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResellerApplicationReceivedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $application;
    public string $reviewUrl;

    public function __construct(array $application, string $reviewUrl = '')
    {
        $this->application = $application;
        $this->reviewUrl = $reviewUrl ?: (env('APP_URL', 'http://127.0.0.1:8080') . '/admin/applications');
    }

    public function build()
    {
        $company = $this->application['CompanyName'] ?? $this->application['companyName'] ?? 'New Applicant';

        return $this->subject("New Reseller Partner Application: {$company}")
            ->view('emails.reseller-application-received')
            ->with([
                'applicationId' => $this->application['ApplicationId'] ?? $this->application['applicationId'] ?? 0,
                'companyName' => $company,
                'contactEmail' => $this->application['ContactEmail'] ?? $this->application['contactEmail'] ?? '',
                'website' => $this->application['Website'] ?? $this->application['website'] ?? null,
                'desiredPrefix' => $this->application['DesiredPrefix'] ?? $this->application['desiredPrefix'] ?? null,
                'expectedVolume' => $this->application['ExpectedVolume'] ?? $this->application['expectedVolume'] ?? '100-500',
                'reviewUrl' => $this->reviewUrl,
            ]);
    }
}
