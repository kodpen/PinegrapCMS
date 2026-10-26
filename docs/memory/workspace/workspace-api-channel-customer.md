---
name: workspace-api-channel-customer
description: A channel's customer over the Workspace API and MCP (PR #1766): customer_type and customer_id on channels.create and channels.update, one half alone refused, null clears, the user role's contact rule
---

## Which routes take it?
- `workspace.channels.create` and `workspace.channels.update` (so their MCP tools): `customer_type` (contact, user_account, erp_account: `ws_customer_types()`) and `customer_id`, both nullable. `WorkspaceChannel` already answered with both; GET …/channels/{id}/customer reads the record.
- Before #1766 only the channel form set it: an application could make a channel for a customer but not name the customer.

## How is it checked?
- `ws_api_channel_customer_write()` runs before `api_dry_run_stop()`, so a rehearsal meets the same refusals: `ws_customer_input()` (a kind the owner may choose, `ws_ref_types()`, and a record that exists), all answered 422 on `customer_id`.
- The user role (3) may name only a contact filed in its contact groups (`ws_viewer_contacts_open()`, as its contact search offers them); one outside is refused like a missing one. A channel keeping its customer is not asked again (`ws_channel_customer_of()`).
- One half alone is refused (`ws_api_channel_customer_missing()`, "… is required.") unless it clears: an empty kind, or id 0. null or empty takes the customer off; both left out leave it alone.

## Why _write?
- `ws_api_channel_customer()` (api_customer.php) is the GET handler; PHP lint does not see two files declaring one name, only loading them does (tests/workspace_api_channel_customer_test.php loads both).
