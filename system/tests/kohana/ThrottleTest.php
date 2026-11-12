<?php

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests rate limiting.
 *
 * @package    Kohana
 * @category   Tests
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
#[Group('kohana')]
#[Group('kohana.core')]
#[Group('kohana.core.throttle')]
class Kohana_ThrottleTest extends Unittest_TestCase
{
	/**
	 * Returns a key no other test (or earlier run) has used.
	 */
	protected function key(): string
	{
		return 'test:'.bin2hex(random_bytes(8));
	}

	public function test_allows_up_to_the_limit(): void
	{
		$key = $this->key();

		$this->assertSame(0, Throttle::hits($key, 3600));

		$this->assertTrue(Throttle::allow($key, 3, 3600));
		$this->assertTrue(Throttle::allow($key, 3, 3600));
		$this->assertTrue(Throttle::allow($key, 3, 3600));
		$this->assertFalse(Throttle::allow($key, 3, 3600));
		$this->assertFalse(Throttle::allow($key, 3, 3600));

		$this->assertSame(5, Throttle::hits($key, 3600));
	}

	public function test_keys_are_counted_separately(): void
	{
		$first  = $this->key();
		$second = $this->key();

		$this->assertTrue(Throttle::allow($first, 1, 3600));
		$this->assertFalse(Throttle::allow($first, 1, 3600));
		$this->assertTrue(Throttle::allow($second, 1, 3600));
	}

	public function test_reset_starts_again(): void
	{
		$key = $this->key();

		Throttle::allow($key, 1, 3600);
		$this->assertFalse(Throttle::allow($key, 1, 3600));

		Throttle::reset($key, 3600);

		$this->assertSame(0, Throttle::hits($key, 3600));
		$this->assertTrue(Throttle::allow($key, 1, 3600));
	}

	/**
	 * A new period starts counting from zero.
	 */
	public function test_a_new_period_starts_from_zero(): void
	{
		$key = $this->key();

		// Start right after a second begins, so the period cannot end between the two calls
		usleep((int) ((1 - fmod(microtime(true), 1)) * 1000000) + 20000);

		$this->assertTrue(Throttle::allow($key, 1, 1));
		$this->assertFalse(Throttle::allow($key, 1, 1));

		usleep(1050000);

		$this->assertTrue(Throttle::allow($key, 1, 1));
	}

	public function test_check_stops_with_429_and_retry_after(): void
	{
		$key = $this->key();

		Throttle::check($key, 1, 3600);

		try
		{
			Throttle::check($key, 1, 3600);
			$this->fail('The second hit was not stopped');
		}
		catch (HTTP_Exception_429 $e)
		{
			$response = $e->get_response();

			$this->assertSame(429, $response->status());
			$this->assertGreaterThan(0, (int) $response->headers('retry-after'));
			$this->assertLessThanOrEqual(3600, (int) $response->headers('retry-after'));
		}
	}

	public function test_retry_after_is_the_rest_of_the_period(): void
	{
		$wait = Throttle::retry_after(60);

		$this->assertGreaterThanOrEqual(1, $wait);
		$this->assertLessThanOrEqual(60, $wait);
	}
}
