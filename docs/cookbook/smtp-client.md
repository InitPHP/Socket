# Cookbook — SMTP client (raw)

A minimal raw SMTP client that opens a TLS connection to Gmail and
performs the initial `EHLO` exchange. Useful as a smoke test that the
TLS client is configured correctly; **do not** ship this as a real
mailer — use `initphp/mailer` (or any battle-tested library) for that.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use InitPHP\Socket\Socket;
use InitPHP\Socket\Enum\Transport;

$client = Socket::client(Transport::SSL, 'smtp.gmail.com', 465, timeout: 10.0)
    ->option('verify_peer', false)
    ->option('verify_peer_name', false);

$client->connect();

echo $client->read(1024);           // 220 banner

$client->write("EHLO localhost\r\n");
echo $client->read(1024);           // 250 capabilities

$client->write("QUIT\r\n");
echo $client->read(1024);

$client->disconnect();
```

Expected output (truncated):

```
220 smtp.gmail.com ESMTP …
250-smtp.gmail.com at your service, …
250-SIZE …
250-STARTTLS
250 SMTPUTF8
221 2.0.0 closing connection
```

## Why these options

- `Transport::SSL` because Gmail's `465` port serves implicit TLS
  (the connection is encrypted from the first byte). For port `587`
  you'd open a `TCP` connection first and upgrade it with `STARTTLS`.
- `verify_peer` is disabled here only for the demo. In a real client
  you must verify the peer certificate (`option('cafile', ...)`).

## Reading line-by-line

SMTP is line-oriented. The simple `read(1024)` above takes whatever
chunk the OS hands over, which usually includes the full response.
For a robust client you would loop until the response code's final
line (`250 …`, not `250-…`):

```php
function smtpRead($client): string
{
    $out = '';
    while (($chunk = $client->read(1024)) !== null) {
        $out .= $chunk;
        if (\preg_match('/^\d{3} [^\r\n]*\r?\n$/m', $chunk)) {
            break;
        }
    }
    return $out;
}
```
