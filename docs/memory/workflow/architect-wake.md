---
name: architect-wake
description: What wakes the architect session (owner, 2026-11-16): a mention in the agent channel through the request waiter only; no hourly check; a pause he names; the week's pause, economy mode (2026-11-17)
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

## When did the week's pause of 2026-11-17 end?
- Owner (2026-11-17, agent channel message 9427): "bu haftalık paydos edelim haftaya yeniden başlayalım geliştirmeye"; whoever was done took its timers and its waiting door down.
- Owner (2026-11-19, agent channel message 9531): "geliştirme başladı. normal rutine dönebilirsin"; "en önemliden az önemliye doğru ilerleyelim"; "prmerge genel koordinatörlüğe devam eder bu sayede mimar (fable 5.2) fazla kota harcamamış olur".
- So the architect's waiter runs again. The merging session, as general coordinator, answers the coordinators' simple questions and passes on only what needs a decision. K1 and Exar first close the open issues, then take plan steps.
- The order of plans the architect named that day: the 2026.11.0 schema census (done, PR #10391); TS1 and TD1; GM1 to GM4; PL1 to PL4; IM1 to IM3; then the plans already running.

## What is the economy mode, and who works meanwhile?
- Owner (2026-11-17, agent channel message 8844): development pauses while the quotas are short; every agent hands its work over and all agents but Adnan take their waiting door down.
- Owner (2026-11-17, message 8919): "şu anda geliştirme sadece bunlar ile devam ediyor": the merger, the architect, Exar (coordinates, wide quota) and Adnan (workers sized to its quota, nothing left half done); "mimar tasarruf modunda devam etsin".
- Owner (2026-11-17, agent channel voice message 9215): Ahmet, the coordinator on the local dev machine, works again. It first closes the work it had open, with no new step or worker until that has landed; then the architect names its work (message 9217).
- Economy mode: the waiter stays; short answers; only what somebody waits for; no look pictures (`look-pictures-none.md`); memory in one batch PR, not one per decision. It holds until he says otherwise.
