<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso\Commands;

use App\Vito\Plugins\Plugnomdev\VitoSso\Secret;
use App\Vito\Plugins\Plugnomdev\VitoSso\SsoToken;
use Illuminate\Console\Command;

/** For testing: prints a sign-in link exactly like Plugnom's API will. */
class LinkCommand extends Command
{
    protected $signature = 'plugnom:sso-link {email} {--redirect=}';

    protected $description = 'Print a 60-second, single-use sign-in link for a (non-admin) Vito user';

    public function handle(): int
    {
        $token = SsoToken::make(Secret::ensure(), (string) $this->argument('email'), $this->option('redirect') ?: null);
        $this->line(rtrim((string) config('app.url'), '/').'/plugnom/sso?token='.$token);

        return self::SUCCESS;
    }
}
