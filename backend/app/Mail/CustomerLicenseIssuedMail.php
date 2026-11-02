<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerLicenseIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $serialKey,
        public readonly string $productName,
        public readonly string $customerEmail,
        public readonly string $gateway,
        public readonly string $transactionId
    ) {}

    public function build(): self
    {
        return $this->subject("Your {$this->productName} License Key")
            ->view('emails.customer-license-issued')
            ->with([
                'serialKey' => $this->serialKey,
                'productName' => $this->productName,
                'customerEmail' => $this->customerEmail,
                'gateway' => $this->gateway,
                'transactionId' => $this->transactionId,
            ]);
    }
}
