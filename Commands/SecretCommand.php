<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso\Commands;

use App\Vito\Plugins\Plugnomdev\VitoSso\Secret;
use Illuminate\Console\Command;

class SecretCommand extends Command
{
    protected $signature = 'plugnom:sso-secret';

    protected $description = 'Print the secret Plugnom uses to sign Vito sign-in links (creates it if missing)';

    public function handle(): int
    {
        $this->line(Secret::ensure());

        return self::SUCCESS;
    }
}
