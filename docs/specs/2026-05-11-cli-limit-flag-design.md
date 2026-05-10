# CLI `--limit` flag for alt-text generation

| Field | Value |
|---|---|
| **Status** | Approved — ready for plan |
| **Created** | 2026-05-11 |
| **Owner** | Jignesh Bhavani |
| **Related** | `inc/AltText/CLI.php`, `wp travelopia-wp-ai alt-text generate` |

## Problem

A site can hold tens of thousands of images. The existing CLI offers `--ids`, `--missing`, and `--all` — all of them either bounded (`--ids`) or unbounded ("process everything"). There is no way to cap a run at, say, 1,000 images, which leaves three legitimate use cases unserved:

1. **Cost measurement** — run the AI for N images, read the cloud bill, extrapolate to the full library.
2. **Quality dry-run** — run for N images, eyeball the alt text, decide whether to tune the prompt before backfilling.
3. **Chunked backfill** — process the library in scheduled chunks (e.g. 5,000 a night) without flooding Bedrock or hammering the host.

Workarounds today (`--ids` with a hand-picked list, ad-hoc SQL queries to get a slice of IDs) don't scale and miss the point of having a CLI.

## Goals

- One new flag, `--limit=<N>`, on the existing `generate` subcommand.
- Composable with `--missing`, `--all`, `--ids`, and `--batch-size` — no new mutually-exclusive matrices.
- Counts **attempts**, not successes. Each call to `AltText::generate()` consumes one limit slot, regardless of outcome. Conservative for cost measurement.
- Stops precisely at N — even mid-batch — so the user always processes exactly N images when N ≤ total.

## Non-goals

- `--dry-run` (don't write to DB). Out of scope for this PR; can be a follow-up.
- Other CLI knobs (custom prompt, model override). Already filterable via PHP — keep CLI surface small.
- Resumable `--all`. Use `--missing --limit=N` for chunked backfill; `--all` runs are intentionally non-resumable.
- Per-image cost reporting. The user reads the cloud bill themselves.

## Design

### Architecture

Change is contained entirely in `inc/AltText/CLI.php`. No new files, classes, or public APIs in `inc/AltText.php`. Roughly 25 lines of production code.

- `CLI::generate()` — parse and validate `--limit`. Reject non-numeric, zero, and negative values up front via `WP_CLI::error()`.
- `CLI::process_ids( array $ids, ?int $limit )` — slice the supplied list to the first N entries before processing.
- `CLI::process_batched( bool $missing_only, int $batch_size, ?int $limit )` — track an `$attempts` counter; break out of the inner `foreach` (and the outer `do-while`) when `$attempts >= $limit`.

### Behavior matrix

| Combination | Behavior |
|---|---|
| `--missing --limit=N` | Process up to N missing images. Re-runs continue naturally because processed images drop out of the result set. **Recommended for chunked backfill.** |
| `--all --limit=N` | Process the first N images returned by the underlying `WP_Query`. **Not resumable** — re-runs hit the same first N. README will say so. |
| `--ids=1,2,3,4,5 --limit=2` | Process the first 2 of the supplied IDs. Silent respect, not silent ignore. |
| `--limit` + `--batch-size` | Independent. Mid-batch break is allowed: `--limit=75 --batch-size=50` does batch 1 (50 attempts) + 25 of batch 2, then stops. |

### Edge cases

| Input | Outcome |
|---|---|
| `--limit=0` | `WP_CLI::error( 'Limit must be a positive integer.' )` |
| `--limit=-5` | Same |
| `--limit=abc` | Same |
| `--limit` omitted | Existing behavior — no cap |
| `--limit=N` where N ≥ total | Process all, no warning. The natural total is the ceiling. |

### Progress bar

Sized to `min( $total, $limit )` — a `--limit=1000` against 50,000 images shows a 1,000-tick bar that fills cleanly, not a 50,000-tick bar that stops at 2 %.

### Implementation sketch

```php
// CLI::generate()
$limit_raw = $args_assoc['limit'] ?? null;
$limit     = null;
if ( null !== $limit_raw ) {
    if ( ! is_numeric( $limit_raw ) || (int) $limit_raw <= 0 ) {
        WP_CLI::error( __( 'Limit must be a positive integer.', 'travelopia-wordpress-ai' ) );
    }
    $limit = (int) $limit_raw;
}
```

```php
// CLI::process_ids()
if ( null !== $limit ) {
    $ids = array_slice( $ids, 0, $limit );
}
```

```php
// CLI::process_batched() inner loop
$attempts = 0;
$cap      = $limit ?? PHP_INT_MAX;

foreach ( $actionable as $image_id ) {
    if ( $attempts >= $cap ) {
        break 2; // exits foreach + outer do-while
    }
    // … existing generate + counters + progress tick …
    ++$attempts;
}
```

## Testing

New file: `.tests/php/tests/AltText/CLITest.php`. The CLI currently has no test file — this is the first.

Approach: shim `WP_CLI` for tests via the bootstrap so `error()` throws (rather than `exit`), and `log()` / `warning()` capture output. Standard pattern.

Cases:

1. `test_limit_caps_attempts_with_missing` — 100 missing images + `--missing --limit=10` → 10 attempts.
2. `test_limit_caps_attempts_with_all` — same with `--all`.
3. `test_limit_with_ids_truncates_list` — 5 IDs + `--limit=2` → 2 processed.
4. `test_limit_zero_errors` — assert `WP_CLI::error` fires.
5. `test_limit_negative_errors` — same.
6. `test_limit_non_numeric_errors` — same.
7. `test_limit_greater_than_total_processes_all` — 5 images + `--limit=100` → 5 processed, no warning.
8. `test_limit_breaks_mid_batch` — `--limit=75 --batch-size=50` → exactly 75 attempts (covers `break 2`).
9. `test_limit_counts_failures` — make some generations fail; assert failures count toward the cap, not just successes.

Plus the existing 62 PHPUnit tests must keep passing — backward compatibility, no behavior change when `--limit` is omitted.

## Documentation

`README.md` — extend the WP-CLI section:

- Add `--limit=<number>` to the flag table with a one-line description.
- Add a new example block showing the recipe for cost/quality sampling and the recipe for chunked nightly backfill.
- Add a one-line caveat under `--all`: "Not resumable — successive runs reprocess the same first N images. Use `--missing` for chunked backfill."

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| User runs `--all --limit=N` expecting resumable backfill, gets the same first N every night. | README caveat. Consider a runtime warning if `--all --limit` is detected — out of scope here, log it as follow-up. |
| Limit semantics ("attempts") confuse users who assumed "successes". | Help text spells out "attempts (success or failure)". |
| Mid-batch break leaves the rest of the queried batch unused (wasted query work). | Acceptable. `process_batched` already pages with reasonable batch sizes; the wasted IDs are bounded by `batch_size`. |

## Out of scope, captured for later

- `--dry-run` flag. Pairs naturally with `--limit` for the quality-review use case. Separate PR if desired.
- Runtime warning when `--all --limit` is used (resumability gotcha).
- A `sample` subcommand to make sampling intent explicit. Considered and rejected during brainstorm — adds duplicated code paths for marginal UX gain.

## Open questions

None at design time. Implementation plan will surface any.
