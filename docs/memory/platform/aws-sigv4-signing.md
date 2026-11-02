---
name: aws-sigv4-signing
description: includes/fn/aws_sign.php: AWS Signature Version 4 without an SDK (header signing, presigned links), tested against the AWS test suite; used by Amazon SES, written for object storage too
module: aws_sign
---

## What does it do?
- Signs a request to an AWS API or an S3-compatible store with an access key (PR #5348, written for the SES mail transport with the names the object-storage plan's OS1 step gives, so OS1 reuses it).
- Pure steps: `pg_aws_canonical_path()` (decoded path encoded per RFC 3986; dot segments and repeated slashes removed unless `normalize` is off, which S3 keys need), `pg_aws_canonical_query()` (decode, encode, sort by name then value), `pg_aws_canonical_headers()` (lower-case, trimmed, inner spaces collapsed, repeats joined by commas, sorted), `pg_aws_canonical_request()`, `pg_aws_scope()`, `pg_aws_string_to_sign()`, `pg_aws_signing_key()`.
- `pg_aws_authorization($key, $region, $service, $method, $url, $headers, $body, $amz_date, $options)` answers the header lines to add: `X-Amz-Date`, `X-Amz-Content-Sha256` (unless `sign_body` is false), `X-Amz-Security-Token` for a session token, `Authorization`. Host is taken from the URL when not given.
- `pg_aws_presign($key, $region, $service, $method, $url, $expires, $headers, $payload_hash, $amz_date, $normalize)` answers the URL with the signature in its query; `UNSIGNED-PAYLOAD` by default (S3), expiry clamped to 1..604800 seconds.

## Where is it loaded?
- Not by `functions.php`: `includes/mail/ses.php` loads it with `require_once`, as any later user does.

## How is it tested?
- `tests/aws_sign_test.php` runs 13 cases of the AWS SigV4 test suite (`tests/fixtures/aws_sigv4/`, copied unchanged from awslabs/aws-c-auth, Apache 2.0, see its README.txt): canonical request, string to sign and signature by headers, the presigned signature, plus the signing key of the AWS documentation's worked example. A case added there is picked up without a code change.
