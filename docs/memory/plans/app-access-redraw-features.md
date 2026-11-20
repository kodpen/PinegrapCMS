---
name: app-access-redraw-features
description: Plan app-access-redraw, features U1 to U8: the New application sheet in three steps, the starting points and who each acts as, the permission picker with search, groups and the four bulk buttons
---

## How is an application made?
- (U1) "New application" opens a sheet as wide as the edit drawer, in three steps with a step line: 1 What connects, 2 Details and permissions, 3 Connect. Back keeps what was typed; nothing is written before Create at the end of step 2.
- (U2) Step 1 is a grid of cards, each an icon tile, a title, two lines of what it is for and a line "Acts as: …". From `api_app_templates()` (pure): id, icon, title, text, acts-as, kind, ticked permissions, guide.
- (U3) The starting points:
  - AI assistant with sign-in: Claude, ChatGPT or another MCP client; a person signs in on the site and agrees. Acts as that person. No secret. The site's one OAuth app: when it exists the card reads "Set up already" and opens it.
  - Agent in a channel: an AI session that answers as itself when its @handle is called. Key and secret. Acts as the application. Makes the agent and its channel rows too (U11).
  - Automation platform: today's start, its permissions ticked (`api_app_start_choices()`).
  - Own integration: a server or a script with a key and a secret; nothing ticked.
  - Read-only reporting: an integration with every read ticked and no write.
  - Mobile app and team devices: staff sign in with their own panel account. Acts as that person; the permissions are the most a device may reach. No secret.
- (U4) Step 2 shows only what the starting point needs: name, description, the permissions (U6); for an agent the handle, name and channels; under a closed "Advanced" the IP list and the rate limit.
- (U5) Step 3 is the one-time sheet joined with the guide of that starting point (U9): what to copy, where to put it, how to try it. Close asks for "I have copied it" when a secret is shown.

## How are permissions chosen?
- (U6) One picker for the sheet and the drawer (`includes/api/app_permissions_view.php`). Areas as headed sections with an icon (store, content, design, Workspace, accounting, site), each permission a row: its name, one line of what it lets the application do, and at the row's end one control None / Read / Write (`pg-seg`); a row with one level shows that level alone.
- (U7) Above the rows, staying in sight while they scroll: a search box, the count "12 of 29 chosen", and four buttons: All, None, All read, All write. Each area's head has the same four in a menu. `api_app_bulk_choice($groups, $which)` (pure, with its script twin) gives the result: All read ticks read where a row has it and nothing where it has only write; All write ticks the highest level of every row.
- (U8) A row that moves money carries a pill saying so; All and All write name how many such rows they ticked. A permission the owner's own account lacks is shown off with the reason, as today. The posted names stay those the save reads.
