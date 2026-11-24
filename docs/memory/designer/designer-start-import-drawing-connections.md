---
name: designer-start-import-drawing-connections
description: The Figma page of Connections as drawn (connections.html, boards A1 to A6): the three cards, the personal access token field, the token's states and the "Disconnect" question
---

## How is the Figma page of Connections laid out?
- `connections.php?service=figma`, breadcrumb "System tools › Connections › Figma", the head with the service's tile, "Figma" and the state pill. The three cards of every service page in their order, no fourth card (`ui/connections-service-page-layout.md`).
- "Status": not connected reads "No access token is stored" with the pill "Not connected"; connected shows the account's initials, "Connected as <name>", the e-mail and when it was last tried, the pill "Connected" and "Disconnect" as the page's only red button.
- "Connection details": one field, "Personal access token", placeholder `figd_…`; a stored token is never shown, the field reads "Stored. Leave empty to keep it." Foot: "Save" filled, "Save and try" quiet.
- The help line under the field says where the token is made (Figma: Settings, Security, "Generate new token"), the scopes "File content" and "Current user", both read only, and the link "Open Figma's settings".
- "Where it is used": one row, "Page designer: start from a Figma file", with its state ("Off", "On", "Paused") and the link "New design".
- The page has no "Connect" button that leaves the site: a personal access token needs no app and no return address.

## What do the token's states say?
- "Save and try" asks Figma whose token it is and fills the Status card's name; "Save" alone stores it and shows "Not tried yet". A token with the "File content" scope only is stored too: the card reads "Connected" with no name.
- No longer accepted (found when Figma refuses a read): pill "Needs attention", "Figma no longer accepts the stored token", and the line that it has expired or was removed in Figma and where to make a new one. The row of "Where it is used" reads "Paused".
- Refused on saving: the field turns red with "Figma refused this token. Check that it was copied whole and has not expired."; nothing is stored. A token without the file scope: "This token cannot read files. Make one with the scopes File content and Current user."
- Figma not reached: "Figma did not answer. The token was not saved; try again."

## What does "Disconnect" ask?
- A question dialog, "Disconnect Figma?", with "Cancel" quiet and "Disconnect" red.
- It says the stored token is removed from this site, designs already made from Figma files stay, the start is off until a token is saved again, and the token stays valid in Figma until it is removed there.

## What changes at 390 px?
- The cards keep their order; the Status card's pill and "Disconnect" go to a second line; a card's buttons stack full width, the filled one first.
