---
name: cloud-file-picker-onedrive
description: OneDrive in Upload File's cloud picker (#3261): the v8 picker with OneDrive/SharePoint tokens per resource, the Graph download through its pre-authenticated address, the Entra setup
module: cloud_file_picker
---

## How does the OneDrive picker open?
- Picker key `onedrive`, connected-accounts provider `microsoft` (`pg_cloud_picker_providers()['onedrive']['connect']`). Purpose `file_picker` asks Microsoft for `https://graph.microsoft.com/Files.Read` only (read-only).
- On click the browser opens a window at once (pop-up rule), asks `cloud_action=token` with `cloud_provider=onedrive`; the server reads Graph `/me/drive` (`driveType`, `webUrl`) and `pg_cloud_picker_onedrive_drive()` answers where the picker opens: personal → `https://onedrive.live.com/picker`, token scope `OneDrive.ReadOnly`; business → `https://<tenant>-my.sharepoint.com/_layouts/15/FilePicker.aspx`, token `https://<host>/.default`.
- The window gets a form POST (`filePicker` settings in the query, `access_token` field); then the MessageChannel: `initialize` → `activate`; each `authenticate` command posts its `resource` back to the token route, `pg_cloud_picker_onedrive_scopes()` gives a token only for the same tenant's SharePoint hosts (`contoso.` / `contoso-my.sharepoint.com`), a personal account always `OneDrive.ReadOnly`; `pick` sends `{drive_id, item_id}`.

## How is the file taken?
- `pg_cloud_picker_source('onedrive')`: ids `^[A-Za-z0-9!_-]{1,200}$` (personal ids carry `!`) → Graph `/v1.0/drives/{d}/items/{i}?$select=name,size,file`, then `/content`, both with the Graph token. The picker's `@sharePoint.endpoint` is never used.
- No `file` facet (OneNote notebook = `package`, folder) → refused, named (`pg_cloud_picker_onedrive_meta()`).
- `/content` answers 302 to a pre-authenticated address; `api_http_request()` follows no redirect, so `pg_cloud_picker_fetch()` reads `Location`, accepts only https on `*.sharepoint.com`, `*.1drv.com`, `*.microsoftpersonalcontent.com`, `*.livefilestore.com` (`pg_cloud_picker_onedrive_download_url()`) and downloads it without the token, with Google's size limit, `write` stream and private-address guard.

## Why does a missing consent not disconnect the account?
- A work tenant that never consented to SharePoint answers the refresh with `invalid_grant` AADSTS65001. `pg_connect_grant_lost()` (`includes/fn/connect.php`) treats only other `invalid_grant`s as a lost permission, so the same account's mail or backups keep working; the picker says an administrator must grant `MyFiles.Read`.

## What does the site owner set up?
- Entra app (Connected accounts › Microsoft, tenant `common`): accounts in any directory and personal accounts; Web redirect `connect.php`; API permissions: Graph `Files.Read`, SharePoint `MyFiles.Read` (delegated), admin consent in work tenants.
- Not verifiable without a live Microsoft account: the picker run itself (check on dev with a personal and a work account).
