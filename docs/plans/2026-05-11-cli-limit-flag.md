# CLI `--limit` Flag — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `--limit=<N>` flag to `wp travelopia-wp-ai alt-text generate` so admins can cap a run at N attempts (cost measurement, quality dry-runs, chunked backfill).

**Architecture:** Single new flag on the existing subcommand. Parsed and validated once in `CLI::generate()`, threaded through to `CLI::process_ids( ?int $limit )` (slice the supplied list) and `CLI::process_batched( … , ?int $limit )` (counter inside the inner foreach, `break 2` when the cap is hit). Progress bar sized to `min( $total, $limit )`. No changes to `inc/AltText.php` or other code paths.

**Tech Stack:** PHP 8.3, WP-CLI 2.12, PHPUnit 9.6, wp-env (Docker), PHPStan max.

**Spec:** `docs/specs/2026-05-11-cli-limit-flag-design.md`.

**Pre-flight:** Branch already exists — `feat/cli-limit-flag` (created off `main`). All work happens on this branch.

**Test runner:** PHPUnit runs inside the wp-env tests-cli container. The `npm run test:php` script is broken on this machine (its `wp-env run` invocation goes through Playground); use `docker exec` directly:

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never"
```

(If the container hash changes, find it via `docker ps --filter 'name=tests-cli'`.)

---

## File Structure

| File | Status | Responsibility |
|---|---|---|
| `inc/AltText/CLI.php` | modify | Parse `--limit`, enforce in batched and IDs paths, size progress bar |
| `.tests/php/tests/MockAdapter.php` | modify | Add `$call_count` static so tests can assert exact number of attempts |
| `.tests/php/tests/AltText/CLITest.php` | new | All CLI behaviour tests (limit semantics, validation, edge cases) |
| `README.md` | modify | Document `--limit`; add cost/sampling and chunked-backfill examples; caveat for `--all --limit` not being resumable |

---

## Task 1: Add `$call_count` counter to MockAdapter

**Why:** Tests need to assert exactly N attempts were made. The existing `$last_call` only tracks the most recent invocation, not the count.

**Files:**
- Modify: `.tests/php/tests/MockAdapter.php`

- [ ] **Step 1: Add the counter property and bump it in `generate_alt_text`**

In `.tests/php/tests/MockAdapter.php`, add a new static property next to `$last_call`:

```php
	/**
	 * Number of times generate_alt_text has been invoked.
	 *
	 * @var int
	 */
	public static int $call_count = 0;
```

Update `reset()` to clear it:

```php
	public static function reset(): void
	{
		self::$mock_response = '';
		self::$last_call     = null;
		self::$call_count    = 0;
		self::$boot_count    = 0;
	}
```

> **Note:** `$boot_count` is added by PR #23 (`fix/plugin-hardening`). If that PR isn't merged when this branch is rebased onto main, drop the `$boot_count` line — the rest of this plan does not depend on it.

Bump the counter at the top of `generate_alt_text`:

```php
	public static function generate_alt_text( string $image_url = '', array $options = [] ): string|WP_Error
	{
		++self::$call_count;
		self::$last_call = compact( 'image_url', 'options' );
		return self::$mock_response;
	}
```

- [ ] **Step 2: Run the existing tests to confirm nothing broke**

Run:

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never"
```

Expected: 62 tests passing (same count as before).

- [ ] **Step 3: Commit**

```bash
git add .tests/php/tests/MockAdapter.php
git commit -m "test: track generate_alt_text call count in MockAdapter"
```

---

## Task 2: Test scaffolding for the CLI

**Why:** No CLI tests exist yet. Set up the test class with a logger that suppresses `WP_CLI` output and turns `error()` into a catchable exception.

**Files:**
- Create: `.tests/php/tests/AltText/CLITest.php`

- [ ] **Step 1: Write the test scaffolding (no test cases yet)**

Create `.tests/php/tests/AltText/CLITest.php`:

```php
<?php
/**
 * Tests for AltText CLI.
 *
 * @package travelopia-wordpress-ai
 */

namespace Travelopia\WordPress_AI\Tests\AltText;

use RuntimeException;
use Travelopia\WordPress_AI\Adapter;
use Travelopia\WordPress_AI\AltText\CLI;
use Travelopia\WordPress_AI\Tests\MockAdapter;
use WP_CLI;
use WP_UnitTestCase;

class CLITest extends WP_UnitTestCase
{
	/**
	 * Set up — register mock adapter and a quiet WP_CLI logger.
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();
		Adapter::reset();
		MockAdapter::reset();
		Adapter::register( 'mock', MockAdapter::class );
		Adapter::set( 'mock' );
		MockAdapter::$mock_response = 'alt';

		WP_CLI::set_logger( new CLITestLogger() );
	}

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void
	{
		Adapter::reset();
		MockAdapter::reset();
		parent::tearDown();
	}

	/**
	 * Create N image attachments and return their IDs.
	 *
	 * @param int $count Number of images to create.
	 *
	 * @return int[]
	 */
	private function create_images( int $count ): array
	{
		$ids = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$ids[] = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		}
		return $ids;
	}
}

/**
 * Quiet WP_CLI logger that throws on error() so PHPUnit can catch it.
 */
class CLITestLogger
{
	/**
	 * @param string $message Error message.
	 * @return void
	 */
	public function error( string $message ): void
	{
		throw new RuntimeException( $message );
	}

	public function warning( string $message ): void {}
	public function success( string $message ): void {}
	public function info( string $message ): void {}
	public function log( string $message ): void {}
	public function debug( string $message, string $group = '' ): void {}
}
```

- [ ] **Step 2: Run PHPUnit and confirm the new file is discovered (zero tests in it, all old tests still pass)**

Run:

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never"
```

Expected: 62 tests, 0 failures. (No tests yet in `CLITest`.)

- [ ] **Step 3: Commit**

```bash
git add .tests/php/tests/AltText/CLITest.php
git commit -m "test: add CLI test scaffolding with WP_CLI logger shim"
```

---

## Task 3: `--limit` caps attempts in `--missing` mode (RED → GREEN)

**Files:**
- Modify: `.tests/php/tests/AltText/CLITest.php`
- Modify: `inc/AltText/CLI.php`

- [ ] **Step 1: Write the failing test**

Append to `CLITest`:

```php
	/**
	 * --missing --limit=2 caps attempts at exactly 2 even when more images are missing.
	 *
	 * @return void
	 */
	public function test_limit_caps_attempts_with_missing(): void
	{
		$this->create_images( 5 );

		( new CLI() )->generate( [], [ 'missing' => true, 'limit' => 2 ] );

		$this->assertSame( 2, MockAdapter::$call_count );
	}
```

- [ ] **Step 2: Run the test, expect failure**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never --filter=test_limit_caps_attempts_with_missing"
```

Expected: FAIL — `5 is not identical to 2` (no limit enforcement yet, all 5 are processed).

- [ ] **Step 3: Implement `--limit` parsing in `CLI::generate()`**

In `inc/AltText/CLI.php`, replace `generate()` body. Add limit parsing before the routing:

```php
	public function generate( array $args = [], array $args_assoc = [] ): void
	{
		$missing_only   = isset( $args_assoc['missing'] );
		$batch_size_raw = $args_assoc['batch-size'] ?? AltText::DEFAULT_BATCH_SIZE;
		$batch_size     = is_numeric( $batch_size_raw ) ? (int) $batch_size_raw : AltText::DEFAULT_BATCH_SIZE;

		$limit_raw = $args_assoc['limit'] ?? null;
		$limit     = null;
		if ( null !== $limit_raw ) {
			if ( ! is_numeric( $limit_raw ) || (int) $limit_raw <= 0 ) {
				WP_CLI::error( __( 'Limit must be a positive integer.', 'travelopia-wordpress-ai' ) );
			}
			$limit = (int) $limit_raw;
		}

		if ( isset( $args_assoc['ids'] ) ) {
			$ids       = explode( ',', (string) $args_assoc['ids'] );
			$image_ids = array_map( 'absint', $ids );
			$this->process_ids( $image_ids, $limit );
			return;
		}

		if ( ! isset( $args_assoc['all'] ) && ! $missing_only ) {
			WP_CLI::error( __( 'Please specify --ids, --missing, or --all.', 'travelopia-wordpress-ai' ) );
		}

		$this->process_batched( $missing_only, $batch_size, $limit );
	}
```

- [ ] **Step 4: Update `process_ids()` signature (slicing comes in Task 4 — for now, just accept the parameter)**

Change signature only:

```php
	private function process_ids( array $image_ids, ?int $limit = null ): void
```

- [ ] **Step 5: Update `process_batched()` to enforce the limit**

Change signature and add the cap check inside the inner foreach. Replace the existing method with:

```php
	private function process_batched( bool $missing_only, int $batch_size, ?int $limit = null ): void
	{
		$total = AltText::count_images( missing_only: $missing_only );

		if ( 0 === $total ) {
			WP_CLI::warning( __( 'No images found to process.', 'travelopia-wordpress-ai' ) );
			return;
		}

		$cap          = $limit ?? PHP_INT_MAX;
		$progress_max = min( $total, $cap );

		WP_CLI::log(
			sprintf(
				/* translators: 1: total images, 2: batch size */
				__( 'Found %1$d images. Processing in batches of %2$d.', 'travelopia-wordpress-ai' ),
				$total,
				$batch_size,
			),
		);

		$success_count = 0;
		$failed_count  = 0;
		$attempts      = 0;
		$start_time    = microtime( true );
		$progress      = make_progress_bar(
			sprintf(
				/* translators: %d: number of images */
				__( 'Processing %d images', 'travelopia-wordpress-ai' ),
				$progress_max,
			),
			$progress_max,
		);

		$page       = 1;
		$failed_ids = [];

		do {
			// --missing re-queries page 1 (processed items drop out of result set); --all uses standard page increment.
			$query_page = $missing_only ? 1 : $page;

			$batch = AltText::query_images(
				missing_only: $missing_only,
				page:         $query_page,
				per_page:     $batch_size,
			);

			if ( empty( $batch ) ) {
				break;
			}

			// Skip already-failed IDs to prevent infinite loops on --missing.
			$actionable = $missing_only ? array_diff( $batch, $failed_ids ) : $batch;

			if ( empty( $actionable ) ) {
				break;
			}

			foreach ( $actionable as $image_id ) {
				if ( $attempts >= $cap ) {
					break 2;
				}

				$result = AltText::generate( $image_id );
				++$attempts;

				if ( $result instanceof WP_Error ) {
					$failed_ids[] = $image_id;
					++$failed_count;
					WP_CLI::warning(
						sprintf(
							/* translators: 1: attachment ID, 2: error message */
							__( 'ID %1$d failed: %2$s', 'travelopia-wordpress-ai' ),
							$image_id,
							$result->get_error_message(),
						),
					);
				} else {
					++$success_count;
				}

				if ( method_exists( $progress, 'tick' ) ) {
					$progress->tick();
				}
			}

			// Free memory between batches.
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}

			++$page;
		} while ( true );

		if ( method_exists( $progress, 'finish' ) ) {
			$progress->finish();
		}

		$this->summary( $success_count, $failed_count, $start_time );
	}
```

- [ ] **Step 6: Run the test, expect pass; run full suite, all green**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never"
```

Expected: 63 tests, all passing.

- [ ] **Step 7: Commit**

```bash
git add inc/AltText/CLI.php .tests/php/tests/AltText/CLITest.php
git commit -m "feat(cli): add --limit flag to cap attempts in batched mode"
```

---

## Task 4: `--limit` truncates the supplied ID list (RED → GREEN)

**Files:**
- Modify: `.tests/php/tests/AltText/CLITest.php`
- Modify: `inc/AltText/CLI.php`

- [ ] **Step 1: Write the failing test**

Append to `CLITest`:

```php
	/**
	 * --ids=A,B,C,D,E --limit=2 processes only the first two IDs.
	 *
	 * @return void
	 */
	public function test_limit_with_ids_truncates_list(): void
	{
		$ids = $this->create_images( 5 );

		( new CLI() )->generate(
			[],
			[
				'ids'   => implode( ',', $ids ),
				'limit' => 2,
			],
		);

		$this->assertSame( 2, MockAdapter::$call_count );
	}
```

- [ ] **Step 2: Run the test, expect failure**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never --filter=test_limit_with_ids_truncates_list"
```

Expected: FAIL — `5 is not identical to 2` (process_ids ignores limit).

- [ ] **Step 3: Implement the slice in `process_ids()`**

In `inc/AltText/CLI.php`, update `process_ids()`:

```php
	private function process_ids( array $image_ids, ?int $limit = null ): void
	{
		if ( empty( $image_ids ) ) {
			WP_CLI::warning( __( 'No images found to process.', 'travelopia-wordpress-ai' ) );
			return;
		}

		if ( null !== $limit ) {
			$image_ids = array_slice( $image_ids, 0, $limit );
		}

		$start_time = microtime( true );
		$counts     = $this->process_batch( $image_ids, count( $image_ids ) );

		$this->summary( $counts['success'], $counts['failed'], $start_time );
	}
```

- [ ] **Step 4: Run the test, expect pass; run full suite**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never"
```

Expected: 64 tests, all passing.

- [ ] **Step 5: Commit**

```bash
git add inc/AltText/CLI.php .tests/php/tests/AltText/CLITest.php
git commit -m "feat(cli): truncate --ids list when --limit is specified"
```

---

## Task 5: Validation — `--limit=0`, negative, non-numeric all error (RED → GREEN)

**Why:** The validation block in `generate()` was added in Task 3, but it's not test-covered. Add explicit tests so future refactors can't silently weaken it.

**Files:**
- Modify: `.tests/php/tests/AltText/CLITest.php`

- [ ] **Step 1: Write three failing tests (one per invalid input)**

Append to `CLITest`:

```php
	/**
	 * --limit=0 raises WP_CLI::error.
	 *
	 * @return void
	 */
	public function test_limit_zero_errors(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/positive integer/i' );

		( new CLI() )->generate( [], [ 'missing' => true, 'limit' => 0 ] );
	}

	/**
	 * --limit=-5 raises WP_CLI::error.
	 *
	 * @return void
	 */
	public function test_limit_negative_errors(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/positive integer/i' );

		( new CLI() )->generate( [], [ 'missing' => true, 'limit' => -5 ] );
	}

	/**
	 * --limit=abc raises WP_CLI::error.
	 *
	 * @return void
	 */
	public function test_limit_non_numeric_errors(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/positive integer/i' );

		( new CLI() )->generate( [], [ 'missing' => true, 'limit' => 'abc' ] );
	}
```

- [ ] **Step 2: Run the three tests, expect all to pass (validation already implemented in Task 3)**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never --filter='test_limit_(zero|negative|non_numeric)_errors'"
```

Expected: 3 tests, all PASS.

> **Note:** This is a "GREEN-only" task — the implementation already exists from Task 3, we're locking in the contract. If any test fails, return to Task 3 and fix.

- [ ] **Step 3: Run full suite**

Expected: 67 tests, all passing.

- [ ] **Step 4: Commit**

```bash
git add .tests/php/tests/AltText/CLITest.php
git commit -m "test(cli): pin --limit validation behaviour for zero/negative/non-numeric"
```

---

## Task 6: `--limit > total` processes all without warning (GREEN-only test)

**Files:**
- Modify: `.tests/php/tests/AltText/CLITest.php`

- [ ] **Step 1: Write the test**

Append to `CLITest`:

```php
	/**
	 * --limit greater than the natural total processes all available images.
	 *
	 * @return void
	 */
	public function test_limit_greater_than_total_processes_all(): void
	{
		$this->create_images( 3 );

		( new CLI() )->generate( [], [ 'missing' => true, 'limit' => 100 ] );

		$this->assertSame( 3, MockAdapter::$call_count );
	}
```

- [ ] **Step 2: Run test, expect pass**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never --filter=test_limit_greater_than_total_processes_all"
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add .tests/php/tests/AltText/CLITest.php
git commit -m "test(cli): pin --limit > total behaviour (process all, no warning)"
```

---

## Task 7: `--limit` breaks mid-batch (GREEN-only test)

**Why:** `--limit=75 --batch-size=50` must stop after exactly 75 attempts (covers the `break 2`). This proves the cap is checked per-image, not per-batch.

**Files:**
- Modify: `.tests/php/tests/AltText/CLITest.php`

- [ ] **Step 1: Write the test**

Append to `CLITest`:

```php
	/**
	 * --limit=15 with --batch-size=10 stops mid-batch (covers break 2).
	 *
	 * @return void
	 */
	public function test_limit_breaks_mid_batch(): void
	{
		$this->create_images( 30 );

		( new CLI() )->generate(
			[],
			[
				'missing'    => true,
				'batch-size' => 10,
				'limit'      => 15,
			],
		);

		$this->assertSame( 15, MockAdapter::$call_count );
	}
```

- [ ] **Step 2: Run test, expect pass**

Expected: PASS — the implementation in Task 3 uses `break 2` so the cap is enforced per-image.

- [ ] **Step 3: Commit**

```bash
git add .tests/php/tests/AltText/CLITest.php
git commit -m "test(cli): pin mid-batch break behaviour for --limit"
```

---

## Task 8: `--limit` counts failures, not just successes (RED → GREEN if needed)

**Why:** The spec is explicit: limit counts attempts, success or failure. If a generation fails, it still consumes a limit slot.

**Files:**
- Modify: `.tests/php/tests/MockAdapter.php` (already counts everything in `generate_alt_text`, no change needed — but verify)
- Modify: `.tests/php/tests/AltText/CLITest.php`

- [ ] **Step 1: Write the test (returns WP_Error from MockAdapter, asserts limit still caps)**

Append to `CLITest`:

```php
	/**
	 * Failures count toward the --limit cap, not just successes.
	 *
	 * @return void
	 */
	public function test_limit_counts_failures(): void
	{
		$this->create_images( 5 );
		MockAdapter::$mock_response = new \WP_Error( 'mock_fail', 'Always fails' );

		( new CLI() )->generate( [], [ 'missing' => true, 'limit' => 3 ] );

		$this->assertSame( 3, MockAdapter::$call_count );
	}
```

- [ ] **Step 2: Run test, expect pass (because Task 3 increments `$attempts` before checking the result)**

Look at the inner loop in `process_batched()` — `++$attempts` happens immediately after `AltText::generate()`, before the result is inspected. So failures count.

If the test fails, return to Task 3 and verify `++$attempts` is in the right place.

> **Note on `--missing` interaction:** `process_batched` skips already-failed IDs in subsequent batches via the `$failed_ids` array. With 5 missing images and a 50-item batch query, all 5 land in batch 1's `$actionable` set. The first 3 fail (consuming 3 limit slots) and `break 2` fires before the next two are processed. Count = 3. ✓

- [ ] **Step 3: Commit**

```bash
git add .tests/php/tests/AltText/CLITest.php
git commit -m "test(cli): pin failure counting behaviour for --limit"
```

---

## Task 9: `--limit` works with `--all` (GREEN-only test)

**Why:** Spec says `--limit` is composable with both `--missing` and `--all`. Task 3 verified `--missing`; this verifies `--all`.

**Files:**
- Modify: `.tests/php/tests/AltText/CLITest.php`

- [ ] **Step 1: Write the test**

Append to `CLITest`:

```php
	/**
	 * --all --limit=N caps attempts to N from the front of the result set.
	 *
	 * @return void
	 */
	public function test_limit_caps_attempts_with_all(): void
	{
		$this->create_images( 5 );

		( new CLI() )->generate( [], [ 'all' => true, 'limit' => 2 ] );

		$this->assertSame( 2, MockAdapter::$call_count );
	}
```

- [ ] **Step 2: Run test, expect pass**

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add .tests/php/tests/AltText/CLITest.php
git commit -m "test(cli): pin --all --limit composability"
```

---

## Task 10: Update CLI doc-comments for `--limit`

**Why:** The doc-comment block on `CLI::generate()` is what `wp help travelopia-wp-ai alt-text generate` shows users. Add the flag and one example.

**Files:**
- Modify: `inc/AltText/CLI.php`

- [ ] **Step 1: Add `--limit` to the OPTIONS section, EXAMPLES, and `@synopsis` line**

In the doc-comment immediately above `public function generate()`, replace the block. Find:

```php
	 * [--batch-size=<number>]
	 * : Number of images to process per batch. Default 50.
	 *
	 * ## EXAMPLES
```

Replace with:

```php
	 * [--batch-size=<number>]
	 * : Number of images to process per batch. Default 50.
	 *
	 * [--limit=<number>]
	 * : Maximum number of images to attempt in this run (success or failure).
	 * : Useful for cost measurement, quality dry-runs, and chunked backfills.
	 * : With --ids: truncates the supplied list to the first N entries.
	 * : With --all: not resumable — successive runs reprocess the same first N images.
	 *
	 * ## EXAMPLES
```

Find the EXAMPLES block ending in `--all --batch-size=20`. Append two new examples before the closing `*/`:

```php
	 *     # Cost / quality sample — process 1000 missing, then stop
	 *     wp travelopia-wp-ai alt-text generate --missing --limit=1000
	 *
	 *     # Chunked nightly backfill — 5000 a night via cron
	 *     wp travelopia-wp-ai alt-text generate --missing --limit=5000
```

Update the `@synopsis` line to include `--limit`:

```php
	 * @synopsis [--ids=<1,2,3>] [--missing] [--all] [--batch-size=<number>] [--limit=<number>]
```

- [ ] **Step 2: Run full test suite**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never"
```

Expected: 70 tests, all passing.

- [ ] **Step 3: Commit**

```bash
git add inc/AltText/CLI.php
git commit -m "docs(cli): document --limit flag in subcommand doc-block"
```

---

## Task 11: Update README

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add `--limit` to the flags table and add new examples**

In `README.md`, find the WP-CLI section. Locate the existing example block ending with `wp travelopia-wp-ai alt-text generate --ids=1,2,3`. Replace the WP-CLI commands block with:

```bash
wp travelopia-wp-ai alt-text generate --missing                    # only images without alt text
wp travelopia-wp-ai alt-text generate --all                        # every image
wp travelopia-wp-ai alt-text generate --ids=1,2,3                  # specific attachments
wp travelopia-wp-ai alt-text generate --all --batch-size=20        # smaller batches for memory-constrained envs
wp travelopia-wp-ai alt-text generate --missing --limit=1000       # cost / quality sample — stop after 1000 attempts
wp travelopia-wp-ai alt-text generate --missing --limit=5000       # chunked nightly backfill — re-runs continue naturally
```

Locate the flag table and add a new row for `--limit`:

| Flag | Description |
|---|---|
| `--limit=<number>` | Maximum number of images to attempt in this run (success or failure). With `--ids`, truncates the supplied list. With `--all`, NOT resumable — successive runs reprocess the same first N images; use `--missing` for chunked backfill. |

> **Note:** The exact existing markdown for the table is in PR #23 (commit `f225201`). If that PR is not merged when this branch is rebased, add the table rather than appending a row.

- [ ] **Step 2: Eyeball the rendered diff**

```bash
git diff README.md
```

Confirm the new flag and examples appear in the right sections.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs(readme): document --limit flag and chunked-backfill recipe"
```

---

## Task 12: Quality chain

**Why:** Universal rule — every PR runs PHPStan, PHPCS, ESLint (no JS changes here so should auto-pass), Stylelint (no CSS), tsc (no TS), and the full PHPUnit suite.

- [ ] **Step 1: PHPStan max**

```bash
composer static-analysis
```

Expected: `[OK] No errors`. If errors appear, fix structurally — no `@var` annotations, no baselines.

- [ ] **Step 2: PHP-CS-Fixer auto-format**

```bash
composer format-fix
```

Expected: `Fixed 0 of N files` or a clean re-format. If files were changed, stage and amend the most recent commit:

```bash
git add -u
git commit --amend --no-edit
```

- [ ] **Step 3: PHPCS style lint**

```bash
composer lint
```

Expected: clean. Common warnings to fix here:
- `Generic.Commenting.DocComment.ShortNotCapital` — capitalise the first word of a docblock short description.
- `Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed` — for callback signatures, append `// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameters used via compact.` on the line above.

- [ ] **Step 4: PHPUnit full suite**

```bash
docker exec a5457b59f1cf3d2f29294d9f84d667a2-tests-cli-1 \
  sh -c "cd wp-content/plugins/wordpress-ai && ./vendor/bin/phpunit --colors=never"
```

Expected: 70 tests, all passing.

- [ ] **Step 5: ESLint, Stylelint, tsc (sanity — no JS/CSS/TS changes in this PR)**

```bash
npm run lint:js && npm run lint:css && npm run type-check
```

Expected: all clean.

- [ ] **Step 6: If any of the above forced a fix, commit the fix**

```bash
git add -u
git commit -m "chore: apply quality chain fixes"
```

(Skip if nothing changed.)

---

## Task 13: Push and open PR

- [ ] **Step 1: Push the branch**

```bash
git push -u origin feat/cli-limit-flag
```

- [ ] **Step 2: Open the PR**

```bash
gh pr create --base main --title "Add --limit flag to alt-text generate CLI" --body "$(cat <<'EOF'
## Summary

Adds `--limit=<number>` to `wp travelopia-wp-ai alt-text generate` so admins can cap a run at N attempts. Counts attempts (success or failure). Composable with `--missing`, `--all`, `--ids`, and `--batch-size`. Mid-batch precision — stops at exactly N.

Use cases:
- **Cost measurement** — run N, check the cloud bill, extrapolate.
- **Quality dry-run** — run N, eyeball alt text, tune the prompt.
- **Chunked nightly backfill** — `--missing --limit=5000` via cron; processed images drop out of the result set so re-runs continue naturally.

Spec: `docs/specs/2026-05-11-cli-limit-flag-design.md`.

## Behaviour

| Combo | Outcome |
|---|---|
| `--missing --limit=N` | Process up to N missing images. |
| `--all --limit=N` | First N from the query result. **Not resumable** — README + doc-block warn. |
| `--ids=A,B,C,D,E --limit=2` | First 2 of the supplied list. |
| `--limit` + `--batch-size` | Independent. Mid-batch break supported. |
| `--limit=0`, negative, non-numeric | `WP_CLI::error` — "Limit must be a positive integer." |

Progress bar sized to `min(total, limit)` so a 1000-cap against 50k images shows a 1000-tick bar that fills cleanly.

## Test plan

- [x] PHPUnit — 70 tests, all passing (8 new in `CLITest`)
- [x] PHPStan max — clean
- [x] PHPCS, ESLint, Stylelint, tsc — clean
- [ ] Validate on a Travelopia brand site:
  - `wp travelopia-wp-ai alt-text generate --missing --limit=10` against a real Bedrock key
  - confirm progress bar reads "Processing 10 images" and stops at 10
  - confirm `--missing --limit=N` re-run picks up the next chunk
EOF
)"
```

Expected: prints PR URL.

- [ ] **Step 3: Update task tracker — mark complete**

The plan ends here. PR #25 (or next available) is ready for brand-site validation.

---

## Self-review (writing-plans skill checklist)

**Spec coverage:**

| Spec section | Implemented in |
|---|---|
| Goal: `--limit` flag with attempts semantics | Tasks 3, 9 |
| Composable with `--missing` | Task 3 |
| Composable with `--all` | Task 9 |
| Composable with `--ids` (truncate) | Task 4 |
| Composable with `--batch-size` (mid-batch break) | Task 7 |
| Validation: zero / negative / non-numeric → error | Task 5 |
| `--limit ≥ total` processes all silently | Task 6 |
| Counts failures, not just successes | Task 8 |
| Progress bar sized to `min(total, limit)` | Task 3 (in `process_batched`) |
| Doc-block in `CLI::generate()` updated | Task 10 |
| README flag table + examples updated | Task 11 |
| New file `.tests/php/tests/AltText/CLITest.php` with WP_CLI shim | Task 2 |
| Quality chain run | Task 12 |

All spec sections covered. ✓

**Placeholder scan:** No "TBD", "TODO", or "implement later". Every code step shows the actual code. ✓

**Type consistency:** `?int $limit` parameter is used consistently in `generate()`, `process_ids()`, `process_batched()`. `MockAdapter::$call_count` is `int`, asserted with `assertSame( 2, … )` (also int). ✓
