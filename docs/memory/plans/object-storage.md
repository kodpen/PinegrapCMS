---
name: object-storage
description: Plan (owner 2026-11-02): an S3-compatible object store (S3, R2, B2, MinIO) as a backup target first, then the home of uploaded files behind get_file.php; steps OS1-OS7
---

## What is wrong now?
- Backups copy to Google Drive, OneDrive and Dropbox only (`includes/fn/backup_cloud.php`, `pg_backup_cloud_types()`, chunked uploads through a connected account). A site on its own server or in the Docker image has no S3-compatible target, which is what hosting providers and operators give a server: a bucket with a key pair, no OAuth.
- Every uploaded file lives under `data/` on the web server's disk (`files` rows, served by `get_file.php` after its access checks, derivatives in `data/cache/img/`, `platform/image-derivatives.md`). A container is replaced with its disk, a second web node sees no files, and a media-heavy site fills the server. Nothing in the product can keep the bytes elsewhere.
- Outbound calls have one wrapper (`pg_curl_run()`) and secrets one sealed pattern; missing are the S3 request signing and the two uses.

## What is proposed?
- One client, no library: `includes/fn/aws_sign.php` (SigV4 canonical request, signing key, presigned URLs; pure, tested against the published AWS vectors) and `includes/fn/object_storage.php` (`pg_os_put()`, `pg_os_get()`, `pg_os_head()`, `pg_os_delete()`, `pg_os_list()`, multipart upload above 64 MB) over `pg_curl_run()`. Settings › Storage card: endpoint, region, bucket, key id, secret (sealed), path-style switch, key prefix, "Test" (put, read, delete one probe object). Any S3-compatible service works by endpoint; nothing names a vendor in code.
- Backups: a fourth cloud type `s3` beside the three accounts: the copy after each backup, the remote retention sweep, the restore screen listing remote archives and bringing one into `data/temp` under the upload rules (`platform/backup-upload-extraction.md`).
- Files: an upload is written locally as today and moved to the store by the general job (an upload never waits on the network); a `files` row says where its bytes are; readers that need a path (the image engine, PDF and office code, the editor) get a local cache copy on demand; `get_file.php` keeps every access check and then redirects to a short-lived presigned URL for a file in the store, or streams it when the request must stay on the site (a download with a changed name, a range request from the panel). Derivatives stay a local cache made from the copy. A migration tool moves existing files in batches with a cursor; Disk Usage and System Status count both places.

## Which decisions bind it?
- Legacy unchanged: a site that configures nothing keeps every file local; the `s3` backup type is one more entry, the three accounts stay. A file never changes its address (the same-address rule of `webp-same-address.md`).
- Access checks stay in `get_file.php`: a presigned URL is minted only after them, lives minutes, and is never written into a page or a mail (mails and feeds keep the site address, as `platform/image-same-address.md` asks).
- The SigV4 code is shared with `mail-deliverability.md` MD3 (Amazon SES): the first to land writes `aws_sign.php`, the other reuses it.

## Which questions are open? (architect; 1 before OS3, 2 before OS2)
1. Where a file's place is kept: `files.storage` (ENUM local, s3) plus `files.storage_key`, or a side table; and whether Workspace channel files (`workspace_file_save.php`) join in OS5 or stay local. Recommended: the two columns; Workspace in OS5.
2. Whether a public-folder file may be served straight from a public bucket or CDN host (faster, no PHP) in a later step, or always through the redirect. Recommended: redirect only in this plan; the public host as a later question.

## Where does it start?
- OS1 alone (the client), then OS2 and OS3 side by side. Read `platform/README.md` key rules, `platform/curl-run-wrapper.md`, `core/security-helpers-and-secret-storage.md`, `platform/image-derivatives.md`, `get_file.php`. Steps: `object-storage-steps.md`.
