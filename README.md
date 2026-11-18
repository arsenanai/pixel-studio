# Pixel Studio

An image generator built on [Pixelforge](https://pixelforge.promptedit.dev/docs) for the PromptEdit take-home. Users write a prompt, see what it will cost before they submit, and pay Pixelforge's exact cost plus 25% once the image is done. Failed generations are free.

Laravel 12 · Inertia 2 · Vue 3 · TypeScript · Tailwind · PostgreSQL · Pest · English and Kazakh

- **Deployed:** https://pixel-studio.laravel.cloud (Laravel Cloud: Postgres, object storage, a queue worker and the scheduler)
- **Demo login:** `demo@pixel.studio` / `w4mlhM5qf3B0ahyW`. The operator login for the spend report at `/admin` is in the submission form.

## How the money works

**Every amount is an integer number of nanodollars** (1e-9 USD) in a `bigint` column whose name ends in `_nano`. Pixelforge prices tokens per million at rates like $12.50, which is 12,500 nanodollars per token, and reports costs to nine decimal places. So every per-token price and every provider cost is a whole number, and nothing is ever rounded until it is formatted for display. Decimal strings from the API are parsed digit by digit (`Money::fromUsdString`). There is no float anywhere on the billing path. Money reaches the browser as `{ nano: "381887500", display: "$0.3818875", cents: "$0.38" }`, and the browser never does arithmetic on it. `cents` is only used for headline totals, always next to the exact amount.

**Provider cost** is `Σ tokens × rate ÷ 1,000,000`: summed first, divided once. If a rate ever produced a cost between two nanodollars, `TokenRates::cost()` throws rather than rounds, because rounding would drift from Pixelforge's own records.

**The charge** is the provider cost plus 25%, rounded *up* to the next nanodollar, per generation. Rounding up means a fraction of a nanodollar never undercharges. Rounding each generation, rather than a total, means every charge is a fixed, independently correct line. Summing three generations that cost 6,036, 1 and 1 nano charges 7,549, not the 7,548 you'd get by marking up the total.

**Each generation is billed at its own rate snapshot.** Pixelforge returns the rates it fixed when the generation was created, and that snapshot is what we bill, never current pricing. Current pricing (`GET /v1/pricing`, cached for 10 minutes) is used only for quotes.

**The estimate is a range.** Output tokens and reference-image tokens are exact. Prompt tokens are `ceil(characters / 4)` ±15%, with the bounds rounded outwards so the real count always falls inside. Reasoning tokens span a per-quality min–max. The quote prices the minimum, a typical value (text estimate plus midpoint reasoning) and the maximum the same way a finished generation is priced. The page shows the range, the breakdown, Pixelforge's cost and our markup line.

**Failed generations cost $0**, whether they failed at Pixelforge or were rejected before being created. Profit is never stored: it is `charge − provider cost`, so it can't drift.

## How webhooks and retries stay correct

One rule handles duplicates and out-of-order delivery: **a snapshot is applied only if its status ranks strictly higher than the current one** (`queued` 10 → `processing` 20 → `succeeded`/`failed` 30). A repeated `succeeded` is 30 → 30, a no-op. A retried `processing` after `succeeded` is 20 → 30, ignored. If both outcomes arrive, the first one wins. `ApplyGenerationUpdate` is the only code that writes status or cost. It takes a row lock, so the webhook, the poller and the submit job can all race it safely.

- **Signatures.** HMAC-SHA256 over `<t>.<raw body>`, checked with `hash_equals` before the body is decoded, within a 300-second window. Several `v1` values are allowed, so the secret can be rotated. Bad signatures get a 400.
- **Duplicate deliveries.** `webhook_events.provider_event_id` is unique and inserted with `insertOrIgnore` in the same transaction as the transition. A redelivery inserts nothing and returns 204. If applying fails, the event row rolls back too, and Pixelforge's retry starts clean.
- **Webhook before the create response.** The generation row is committed before the submit job runs (`ShouldQueueAfterCommit`). Our UUID is sent as both the `Idempotency-Key` and `metadata.generation_id`, so an early webhook finds the row. When the late `202 queued` lands, it fills in the provider id and can't move the status back. This is tested by delivering the webhook from inside the faked HTTP call.
- **Limits, overloads and timeouts.** The submit job honours `Retry-After` on 429/503, backs off exponentially up to a minute, and retries timeouts with the same key and the same body for up to 15 minutes. A request Pixelforge rejects outright (content policy, validation, daily quota) becomes a failed, free generation with a plain reason.
- **Missed webhooks.** `pixelforge:poll` runs every 30 seconds. It fetches accepted generations that have been quiet for 30 seconds, outside any transaction, and passes them to the same `ApplyGenerationUpdate`. It stops for the tick on 429/503, re-submits generations that never got a provider id, and cancels them for free after 30 minutes.

The webhook is handled synchronously: one insert plus one locked update is far inside Pixelforge's 10-second budget, and queueing it would need a second layer of dedupe for no gain.

## Admin report

`/admin` (operators only) shows what users were charged, what Pixelforge charged us, and the profit, for any range up to 92 days. A generation counts on the day it *completed*, the day Pixelforge bills it.

- A stacked daily chart (Pixelforge cost + profit = charged) with hover, keyboard reading and click-to-zoom into one day. The daily table underneath is its accessible equivalent.
- Users are paginated, searchable by name or email, and sortable by charged, profit or images. Selecting a user narrows every number on the page to them.
- **Download CSV** streams one row per user per day with exact decimal amounts. Cells that start with `=`, `+`, `-` or `@` are escaped so a user's name can't run as a spreadsheet formula.
- Provider cost and generation count are checked against `GET /v1/usage` for every day in the range, as exact integers, and any day that differs is listed. The check loads after the page renders, so a slow Pixelforge never blocks the report.

```bash
php artisan pixelforge:reconcile --from=2026-11-01 --to=2026-11-16   # exits 1 on any difference
php artisan pixelforge:usage                                          # what Pixelforge billed this key
```

Reconciliation runs every night for the previous day. Usage is per API key, so each environment has its own key and reconciles only its own numbers.

### Built for volume

I loaded 500,000 generations across 5,000 users and 90 days (`php artisan load-test:seed`, refused in production) and measured before changing anything:

| | Before | After |
|---|---|---|
| Report, 30 days | 1.3 s, 26 MB page | 75 ms, 17 KB |
| Report, 92 days | out of memory | 182 ms, 33 KB |
| CSV export, 229k rows | — | 0.7 s, 42 MB peak, streamed |
| History, user with 29.5k images | 6 ms (page 1000: 58 ms) | unchanged, plus filters |
| Credit statement, 28k ledger rows | first 50 only | 90–105 ms on any page |

The report now reads `daily_spend`, one row per user per day. `ApplyGenerationUpdate` adds to it in the same transaction that records the charge, so it can't disagree with the generations. `RebuildDailySpend` recomputes any range from raw rows; the migration uses it to backfill. The report's cost now grows with days × active users, not with every generation ever made.

## Credit balances

Credits are US dollars, held in an append-only `credit_transactions` ledger: purchases are positive and charges negative. A balance is the sum of the ledger and is never stored.

- **Buying** is simulated: the Credits page adds $1, $5 or $20 with no payment.
- **The statement** is paginated and filterable by purchases or charges. Each line shows the balance after it (a window over the user's own ledger) and, for charges, the prompt it paid for.
- **Holding.** Available credit is the balance minus the *estimated maximum* of the user's unfinished generations. Submitting locks the user's row and refuses any generation whose maximum exceeds what is available, so two quick submits can't spend the same money, and whatever an image really costs is already covered.
- **Charging.** When a generation succeeds, the exact charge is debited in the same transaction as the status change. `(generation_id, type)` is unique, so even if the rank rule were bypassed the database refuses a second debit. A failed or rejected generation debits nothing, and its hold ends with its status.

## History, images and drafts

- **History** has status tabs with counts, prompt search and numbered pages. Polling keeps running while anything is unfinished, even when the current filter hides it.
- **Images are copied into our own storage** once a generation succeeds (`StoreGenerationImage`, after the charge commits). Pixelforge's links expire after 24 hours, so this keeps history working and puts loading speed under our control. Only PNGs under 20 MB are stored. Images are served only to their owner (and operators) with long private caching. `pixelforge:store-images` backfills older images whose links are still valid. Copying is on when `IMAGES_DISK` names a durable disk (`local` in development, the `images` R2 bucket in production); without one the app keeps using Pixelforge's links, because Laravel Cloud's own disk is wiped on every deploy.
- **Drafts.** The generate form saves a draft per user in the browser, restores it with a "start over" option, and clears every draft on log out, so a shared computer doesn't keep someone's prompts.
- **Sizes and quality** are shown as their real aspect ratio and a level meter, not just labels.

## Languages

The whole app, including sign-in, profile, validation messages and emails, is in English and Kazakh. The choice is saved on the account, falls back to the session and then the browser's language, and is switched from the header.

- PHP uses `__()` and Vue uses `t()`/`tc()` over the same `lang/kk.json`, so there is one catalogue. Failure reasons are stored in English and translated when shown; provider text we don't recognise passes through unchanged.
- `tests/Unit/TranslationsTest.php` fails the build if any string in the code has no Kazakh translation or a translation drops a placeholder.
- Browsers ship incomplete Kazakh date data (Chrome shows "M10 29"), so Kazakh dates use real month and weekday names. Money keeps one format in both languages, and the font (Inter) covers Kazakh Cyrillic.

## Running it locally

```bash
composer setup                     # install, .env, key, migrate, npm build
php artisan db:seed                # demo user + operator (prints generated passwords)
composer dev                       # server, queue worker, logs and Vite
php artisan schedule:work          # the poller, in another terminal
```

Set `PIXELFORGE_API_KEY` and `PIXELFORGE_SIGNING_SECRET` in `.env` (one key per environment). Without a public tunnel webhooks can't reach localhost, so the poller does their job, a little more slowly.

```bash
composer test        # Pest; Pixelforge is faked and stray HTTP requests fail the test
composer analyse     # PHPStan (Larastan) at level max, no baseline
composer lint        # Pint
npm run type-check   # vue-tsc, strict, no `any`
```

Prompt keywords make Pixelforge misbehave on purpose: `[fail]`, `[blocked]`, `[overloaded]`, `[slow]`, `[duplicate]`, `[succeed]`.

To try the report at volume, point `DB_DATABASE` at a scratch database and run `php artisan migrate && php artisan load-test:seed` (5,000 users and 500,000 generations by default; never in production, since synthetic rows would break reconciliation).

## Decisions and trade-offs

- **Nanodollars, not decimals or floats.** Integers make exactness a property of the type, and `bigint` holds about $9.2 billion.
- **Queue the submit; don't call Pixelforge in the request.** Retries become safe and invisible, and the form stays fast. The cost is that a rejected prompt shows up as a failed row in history, not a form error.
- **Polling the history page instead of websockets.** The page reloads its own props every 2.5 seconds, and only while something on it is unfinished. That is plenty for this, and there is no extra infrastructure to run.
- **A rollup table for the report, written by the billing transaction.** The alternative, a scheduled aggregation, would lag and need its own catch-up logic. Writing it next to the charge makes it exact by construction, and the rebuild action covers backfills.
- **A hand-built SVG chart, no chart library.** One stacked column chart doesn't justify a dependency, and owning the markup made keyboard reading, the hover target and the colour-blind-checked palette straightforward.
- **Drafts in the browser, not the database.** A draft is a convenience for one device. Server drafts would sync across devices but add a table and an endpoint for little gain.
- **Tests run on in-memory SQLite.** `lockForUpdate()` is a no-op there, so the concurrency guarantee comes from the lock plus the rank rule, and is exercised by sequential race tests. Migrations stay portable (`json`, string-backed enums).
- **Hand-written DTO hydration.** Every Pixelforge field passes through one typed reader that names the bad path when a shape is wrong. `mixed` stays in that one class.

## Known limits

- If a create request times out, Pixelforge actually creates the generation, and every later retry for 30 minutes is rejected before it can replay, the row is cancelled as free while Pixelforge bills it. Reconciliation would show that day as different. In practice any replay inside the 30 minutes recovers it.
- A generation Pixelforge reports as `404` (for example after a key change) is logged and left pending rather than guessed at.
- I wrote the Kazakh translation myself; a native speaker should review it before real users see it.

## What I'd do next

- Store the running balance on each ledger row (written under the user lock) if any user's statement grows far past 30k entries.
- Move the analytics side of the report to an analytical store such as ClickHouse once the daily rollup itself gets large.
- Metrics and alerts for signature failures, submit give-ups, reconciliation drift and queue depth.
- Reverb websockets if status updates ever need to be instant.
