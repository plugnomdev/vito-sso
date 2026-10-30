<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso;

use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterCommand;
use App\Vito\Plugins\Plugnomdev\VitoSso\Api\ApiController;
use App\Vito\Plugins\Plugnomdev\VitoSso\Api\VerifyPlugnomRequest;
use App\Vito\Plugins\Plugnomdev\VitoSso\Commands\LinkCommand;
use App\Vito\Plugins\Plugnomdev\VitoSso\Commands\SecretCommand;
use Illuminate\Support\Facades\Route;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Plugnom SSO';

    protected string $description = 'Plugnom dashboard integration: sign-in links, customer accounts and attaching customer VPSs.';

    public function boot(): void
    {
        Route::middleware('web')
            ->get('/plugnom/sso', SsoController::class)
            ->name('plugnom.sso');

        // Server-to-server, signed by Plugnom's API — no session, no CSRF.
        Route::prefix('plugnom/api')
            ->middleware(VerifyPlugnomRequest::class)
            ->group(function (): void {
                Route::get('/ssh-key', [ApiController::class, 'sshKey']);
                Route::post('/accounts', [ApiController::class, 'account']);
                Route::post('/servers', [ApiController::class, 'attachServer']);
                Route::get('/servers/{server}', [ApiController::class, 'showServer'])->whereNumber('server');
                Route::delete('/servers/{server}', [ApiController::class, 'detachServer'])->whereNumber('server');
            });

        RegisterCommand::make(SecretCommand::class)->register();
        RegisterCommand::make(LinkCommand::class)->register();
    }

    public function install(): void
    {
        Secret::ensure();
    }
}
