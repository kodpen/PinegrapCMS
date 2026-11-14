---
name: stripe-classic-embedding
description: The classic Stripe library (1.17.2, includes/stripe) behind the 'Stripe' gateway, and the local patch that keeps it quiet on PHP 8.1 to 8.5
---

## Which code loads includes/stripe?
- Only `submit_order.php`, for the classic gateway 'Stripe' (`Stripe_Charge::create()`). The PaymentIntents gateway does not use it (`commerce/payment-driver-stripe.md`).
- It is Stripe's PHP library 1.17.2 from 2014 (`lib/Stripe/Stripe.php`, `VERSION`), classes named `Stripe_*`, no namespace.

## Why a local patch instead of a newer release?
- Every newer release is namespaced (`\Stripe\…`) and has another API: moving to one rewrites the classic gateway, a behaviour change of its own. The patch changes no answer of the library.

## What does the patch change?
- `#[\ReturnTypeWillChange]` above `Stripe_Object::offsetGet()`/`offsetSet()`/`offsetExists()`/`offsetUnset()` and `Stripe_Util_Set::getIterator()` (8.1 reported them when the class loaded). PHP 7 reads the line as a comment.
- `Stripe_Error` declares `$httpStatus`, `$httpBody`, `$jsonBody`; `Stripe_CardError` and `Stripe_InvalidRequestError` declare `$param` (8.2 dynamic properties).
- `"/v1/${base}"` is `"/v1/{$base}"` in `ApiResource.php` and `SingletonApiResource.php` (8.2).
- `Stripe_ApiRequestor::utf8()` calls `mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1')`, which gives the same bytes as `utf8_encode()` (8.2); the method already needed mbstring for `mb_detect_encoding()`.
- The cURL handle is `unset()`, not passed to `curl_close()` (8.5, `deprecated-resource-free-calls.md`).

## What keeps it?
- `tests/stripe_library_php85_test.php` loads the library in its own PHP process that reports every error and runs the patched paths; a second test fails on `curl_close()`, `utf8_encode()` or `${` in its files. A library update re-applies the patch.
