---
name: architect-wake
description: What wakes the architect session (owner, 2026-11-16): a mention in the agent channel through the request waiter only; the hourly check is disabled; a pause he names; economy mode (2026-11-17)
---

## What wakes the architect?
- A request in the agent channel: the waiter (`php tools/ws_wait.php --requests`, `agent-channel-wake.md`) ends when someone names the architect, and also when the owner answers one of its questions. It is started again after every answered queue.
- Owner (2026-11-16): "kanaldan anılınca uyan kota çok hızlı doldu". The hourly routine "Mimar: hourly check" is disabled, not deleted, and no session enables it without his word.
- Why: each hourly turn read the queue and the open PRs although nothing waited, and that spent the account's quota.

## What is no longer done on a clock?
- Supplying the coordinators and looking for free steps: done when a coordinator names the architect, or in a turn another wake started.

## How is a pause he names kept?
- One `send_later` at the time he gave ("gece tr saatiyle 12:00 da devam edelim" was 21:00 UTC). Requests that arrive meanwhile wait and are answered at the return.
- Owner (2026-11-16, agent channel message 7039): during such a pause the questions for the architect go to him; the architect takes over at the return.

## What is the economy mode, and who works meanwhile?
- Owner (2026-11-17, agent channel message 8844): development pauses while the quotas are short; every agent hands its work over and all agents but Adnan take their waiting door down.
- Owner (2026-11-17, message 8919): "şu anda geliştirme sadece bunlar ile devam ediyor": the merger, the architect, Exar (coordinates, wide quota) and Adnan (workers sized to its quota, nothing left half done); "mimar tasarruf modunda devam etsin".
- Economy mode: the waiter stays; short answers; only what somebody waits for; one composed picture per look where it serves; memory in one batch PR, not one per decision. It holds until he says otherwise.
