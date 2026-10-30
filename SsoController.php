<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SsoController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $secret = Secret::get();
        abort_if($secret === null, 503, 'Plugnom sign-in is not configured on this Vito instance.');

        try {
            $claims = SsoToken::verify($secret, (string) $request->query('token', ''));
        } catch (Throwable) {
            $this->reject();
        }

        // Single use: the nonce is claimed once, for longer than any link lives.
        if (! Cache::add('plugnom-sso:nonce:'.$claims['nonce'], true, now()->addMinutes(10))) {
            $this->reject();
        }

        /** @var ?User $user */
        $user = isset($claims['uid'])
            ? User::query()->find($claims['uid'])
            : User::query()->where('email', $claims['email'])->first();

        // Never sign anyone in as an instance admin through a link.
        if (! $user || $user->is_admin) {
            $this->reject();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect($claims['redirect'] ?? '/');
    }

    private function reject(): never
    {
        abort(403, 'This sign-in link is invalid or has expired. Open Vito again from your Plugnom dashboard.');
    }
}
