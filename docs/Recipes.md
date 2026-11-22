# Recipes

Small, runnable end-to-end examples. Each links to a focused page with the full code and explanation.

| Recipe | Transport | What it shows |
|---|---|---|
| **[Recipe Echo Server](Recipe-Echo-Server.md)** | TCP, UDP | The simplest possible request / response server. |
| **[Recipe Chat Server](Recipe-Chat-Server.md)** | TCP | `register()` for named clients, `broadcast()` for fan-out, `quit` command. |
| **[Recipe Broadcast](Recipe-Broadcast.md)** | TCP & UDP | The three flavours of `broadcast()`: all, by-id, by-list. |
| **[Recipe Self Signed TLS](Recipe-Self-Signed-TLS.md)** | TLS | Generate a cert in pure PHP, bind a TLS server, connect with a TLS client. |
| **[Recipe SMTP Client](Recipe-SMTP-Client.md)** | SSL | Raw `EHLO` exchange against Gmail's port 465. |
| **[Recipe Custom Channel](Recipe-Custom-Channel.md)** | — | Implement your own `ChannelInterface` (in-memory, in-process pipe, …). |

## Conventions in these recipes

- Every recipe assumes `composer require initphp/socket` has run and the `vendor/autoload.php` is loaded.
- Loopback (`127.0.0.1`) is used for everything that needs a host so the snippets run unmodified.
- TLS recipes generate self-signed material to keep the snippets self-contained. **Do not ship that** — see [SSL Context Options](SSL-Context-Options.md) for production-grade configuration.
- The recipes prefer `live()` for the canonical pattern. To embed them in your own event loop, swap `live()` for a `while (… $server->tick(…)) { … }` shape — see [Event Loop Integration](Event-Loop-Integration.md).
