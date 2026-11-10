<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductsExportEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $storeName) {}

    public function build(): self
    {
        return $this->subject('Your product export is ready')
            ->html('<p>The product export for '.$this->storeName.' is ready.</p>');
    }
}
