# Plugnom SSO for VitoDeploy

Connects a Vito instance to Plugnom: customers open their Vito account from
the Plugnom dashboard without a separate password, and Plugnom's API creates
their account and attaches the VPSs they order.

## How a link works

`https://<vito>/plugnom/sso?token=<token>`, where the token is
`base64url(claims) . "." . base64url(HMAC-SHA256(secret, base64url(claims)))`
and the claims are `uid` (Vito user id; `email` is accepted for testing),
`exp` (unix seconds), `nonce` and an optional `redirect` path.

A link is refused when:

- the signature doesn't match the shared secret;
- it has expired, or claims to live longer than 120 seconds;
- its nonce has been used before (links are single-use);
- the redirect isn't a plain path on this Vito instance;
- the email isn't a Vito user, or is an instance **admin** — admins never
  sign in through a link.

## API (server-to-server, Plugnom only)

Every request carries `X-Plugnom-Timestamp` (unix seconds) and
`X-Plugnom-Signature` = hex `HMAC-SHA256(secret, "{timestamp}.{METHOD}.{request URI}.{raw body}")`,
and is refused if unsigned, wrongly signed or more than 5 minutes old.

| Method | Path | Does |
|---|---|---|
| GET | `/plugnom/api/ssh-key` | Vito's public SSH key, for new VPSs |
| POST | `/plugnom/api/accounts` `{email, name}` | Finds or creates the customer (never an admin); returns `userId`, `projectId` |
| POST | `/plugnom/api/servers` `{userId, name, ip, port?, os, services?}` | Attaches a VPS to the customer's project and starts installing |
| GET | `/plugnom/api/servers/{id}?userId=` | Install status and progress |
| DELETE | `/plugnom/api/servers/{id}?userId=` | Removes the server from Vito (the VPS is untouched) |

`os` is one of `ubuntu_22`, `ubuntu_24`, `ubuntu_26`.

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

## Update

The folder belongs to the `vito` user, so pull as that user (git refuses as
root), rebuild Vito's caches and restart PHP so the new code is served:

```sh
sudo -u vito git -C /home/vito/vito/app/Vito/Plugins/Plugnomdev/VitoSso pull
cd /home/vito/vito && sudo -u vito php artisan optimize
systemctl restart $(systemctl list-units --type=service --no-legend | awk '/php.*fpm/{print $1}')
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
