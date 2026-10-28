---
name: mail-imap-client
description: The socket IMAP client pg_imap_*() (includes/fn/imap_client.php, G2a of incoming e-mail): why no imap extension, TLS and address check, sign-in, commands, error codes, limits, tests
module: imap_client
---

## Why its own client, not PHP's imap extension?
- The `imap` extension left PHP's core in 8.4 and many hosts on 7.1–8.3 never had it, so the client speaks IMAP4rev1 itself over `stream_socket_client()`. It needs only OpenSSL (`extension_loaded('openssl')`); no mbstring or iconv.
- Loaded with `require_once` by the mail code that reads a box, not by `functions.php`.

## How does it connect?
- `pg_imap_connect($host, $port, $options)`: implicit TLS only (993; no STARTTLS, no plain port), certificate and name always checked (`verify_peer`, `verify_peer_name`, SNI, `CURL_CA_BUNDLE` as `cafile` when set). The host is resolved once and checked by `api_http_check_url()` (no loopback, private or metadata address), then the socket goes to that IP with the host as `peer_name`, so a second lookup cannot point elsewhere.
- `timeout` (per read, 10 s) and `budget` (whole session, 15 s, `deadline`) in `$options`.
- Greeting: OK (capabilities kept from its code), BYE is `refused`, PREAUTH is refused too.

## How does it sign in?
- `pg_imap_login()`: `AUTHENTICATE PLAIN` when offered (base64, so any UTF-8 password; in the command line with SASL-IR, else after `+`), else `LOGIN` with astrings; `LOGINDISABLED` without PLAIN is `auth_disabled`. CAPABILITY is asked again after sign-in unless the OK carried it (MOVE, UIDPLUS).
- A password never goes into `error` / `detail`; the detail is the server's words (Gmail's "Application-specific password required" shows there).

## Which commands are there?
- `pg_imap_command($session, $parts)`: tags `A0001…`; a part `array('literal' => …)` goes as `{N}` + go-ahead, or `{N+}` with LITERAL+. `pg_imap_astring()` quotes, or makes a literal for CR, LF, NUL or 8-bit bytes.
- `pg_imap_select()` (EXAMINE when read-only) → exists, uidvalidity, uidnext; `pg_imap_uid_search()`; `pg_imap_uid_fetch()` → uid => item map (`BODY[…]` names keep their section, `pg_imap_fetch_section()` finds one by prefix); `pg_imap_uid_mark_seen()`; `pg_imap_uid_move()` (UID MOVE, else COPY + `\Seen \Deleted` + `UID EXPUNGE` only with UIDPLUS: a plain EXPUNGE would remove others' deleted mail); `pg_imap_logout()` closes the stream.
- Folder names go through `pg_imap_utf7_encode()` (modified UTF-7, own code); `pg_imap_uid_set()` compresses UIDs; `pg_imap_search_date()` is the English `j-M-Y` SINCE wants.

## Which errors and limits?
- `$session['error']`: `connect`, `tls`, `timeout`, `closed`, `refused`, `auth`, `auth_disabled`, `folder`, `protocol`, `too_large`; the first one stays, and a broken session answers every later command at once. `pg_imap_connect_error()` sorts a refused socket (the warning names `ssl://`, so TLS is read from the handshake's own words).
- A literal over `PG_IMAP_MAX_LITERAL` (26 MB) is `too_large` before it is read; a line over 1 MB is `protocol`. Broken data never raises.

## How is it tested?
- `tests/imap_client_test.php`: the tokenizer, parsers, astrings, UTF-7 (RFC 3501 example), and whole sessions against a scripted server over two `php://memory` streams (`pg_imap_session($in, $out)`). A real server was not reached from the cloud session (port 993 is closed there).
