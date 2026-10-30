# Plugnom SSO for VitoDeploy

Lets Plugnom customers open their Vito account from the Plugnom dashboard
without a separate password. Plugnom's API signs a short-lived link; this
plugin checks it and signs the customer in.

## How a link works

`https://<vito>/plugnom/sso?token=<token>`, where the token is
`base64url(claims) . "." . base64url(HMAC-SHA256(secret, base64url(claims)))`
and the claims are `email`, `exp` (unix seconds), `nonce` and an optional
`redirect` path.

A link is refused when:

- the signature doesn't match the shared secret;
- it has expired, or claims to live longer than 120 seconds;
- its nonce has been used before (links are single-use);
- the redirect isn't a plain path on this Vito instance;
- the email isn't a Vito user, or is an instance **admin** — admins never
  sign in through a link.

## Install

On the Vito server, as root:

```sh
mkdir -p /home/vito/vito/app/Vito/Plugins/Plugnomdev
git clone https://github.com/plugnomdev/vito-sso.git /home/vito/vito/app/Vito/Plugins/Plugnomdev/VitoSso
chown -R vito:vito /home/vito/vito/app/Vito/Plugins/Plugnomdev
```

Then in Vito: **Admin → Plugins → Discover → Plugnom SSO → Install**.

Print the shared secret (created on install) and put it in Plugnom's API
environment — never commit it:

```sh
cd /home/vito/vito && sudo -u vito php artisan plugnom:sso-secret
```

## Test

```sh
cd /home/vito/vito
sudo -u vito php artisan user:create "Test Customer" test-customer@plugnom.com 'a-long-password' --role=user
sudo -u vito php artisan plugnom:sso-link test-customer@plugnom.com
```

Open the printed link within 60 seconds: you're signed in as the test
customer. Opening it again is refused.

## License

AGPL-3.0, like VitoDeploy itself.
