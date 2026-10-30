<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso;

use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterCommand;
use App\Vito\Plugins\Plugnomdev\VitoSso\Commands\LinkCommand;
use App\Vito\Plugins\Plugnomdev\VitoSso\Commands\SecretCommand;
use Illuminate\Support\Facades\Route;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Plugnom SSO';

    protected string $description = 'Sign in to Vito from the Plugnom dashboard with a short-lived, single-use link.';

    public function boot(): void
    {
        Route::middleware('web')
            ->get('/plugnom/sso', SsoController::class)
            ->name('plugnom.sso');

        RegisterCommand::make(SecretCommand::class)->register();
        RegisterCommand::make(LinkCommand::class)->register();
    }

    public function install(): void
    {
        Secret::ensure();
    }
}
