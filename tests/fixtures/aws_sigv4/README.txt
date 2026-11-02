AWS Signature Version 4 test vectors, copied unchanged from the AWS signing
test suite in awslabs/aws-c-auth (tests/aws-signing-test-suite/v4, Apache
License 2.0). Each folder holds the request, its context (credentials,
region, service, time) and the expected canonical request, string to sign
and signature for header signing and for a presigned (query) request.
Read by tests/object_storage_sign_test.php.

s3_examples.json holds the Amazon S3 examples from the SigV4 documentation
(GET object with a Range header, a presigned GET URL).
