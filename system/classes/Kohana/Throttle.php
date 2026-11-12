<?php
/**
 * Rate limiting: allows something to happen only a number of times within
 * a period of time.
 *
 * What is counted is named by a key that you choose. It usually says what
 * is limited and for whom:
 *
 *     // At most 60 API calls per minute for each client address
 *     Throttle::check('api:'.Request::$client_ip, 60, 60);
 *
 *     // At most 5 password attempts in 15 minutes for each account
 *     if ( ! Throttle::allow('login:'.$username, 5, 900))
 *     {
 *         // Tell the visitor to wait
 *     }
 *
 * Time is cut into fixed periods (with a period of 60 seconds: one per
 * minute) and the hits of the current period are counted. A new period
 * starts counting from zero.
 *
 * The counters are kept with [Kohana::cache]: in the cache module when it
 * is enabled, otherwise in the cache files of the application. Reading and
 * writing a counter are two steps, so under many simultaneous requests a
 * few hits more than the limit can pass. With several web servers, use a
 * cache they share (Redis, Memcached), or each server counts on its own.
 *
 * @package    Kohana
 * @category   Security
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Throttle {

	/**
	 * Counts a hit and tells whether it is still within the limit.
	 *
	 *     if (Throttle::allow('contact-form:'.Request::$client_ip, 3, 3600))
	 *     {
	 *         // Send the message
	 *     }
	 *
	 * @param   string  $key      what is being limited, and for whom
	 * @param   int     $max      number of hits allowed in one period
	 * @param   int     $seconds  length of the period
	 * @return  bool    true if this hit is allowed
	 */
	public static function allow(string $key, int $max, int $seconds): bool
	{
		return Throttle::hit($key, $seconds) <= $max;
	}

	/**
	 * Counts a hit and stops the request with "429 Too Many Requests" when
	 * the limit is passed. The response tells the client how long to wait
	 * in its Retry-After header.
	 *
	 *     public function before(): void
	 *     {
	 *         parent::before();
	 *
	 *         Throttle::check('api:'.Request::$client_ip, 60, 60);
	 *     }
	 *
	 * @param   string  $key      what is being limited, and for whom
	 * @param   int     $max      number of hits allowed in one period
	 * @param   int     $seconds  length of the period
	 * @return  void
	 * @throws  HTTP_Exception_429  when the limit is passed
	 */
	public static function check(string $key, int $max, int $seconds): void
	{
		if ( ! Throttle::allow($key, $max, $seconds))
		{
			/** @var HTTP_Exception_429 $e */
			$e = HTTP_Exception::factory(429, 'Too many requests. Try again in :seconds seconds.', [
				':seconds' => Throttle::retry_after($seconds),
			]);

			$e->retry_after(Throttle::retry_after($seconds));

			throw $e;
		}
	}

	/**
	 * Counts a hit and returns how many there have been in the current period.
	 *
	 * @param   string  $key      what is being limited, and for whom
	 * @param   int     $seconds  length of the period
	 * @return  int
	 */
	public static function hit(string $key, int $seconds): int
	{
		$name = Throttle::_cache_name($key, $seconds);
		$hits = Throttle::hits($key, $seconds) + 1;

		// The counter is only needed until its period ends
		Kohana::cache($name, $hits, max(1, $seconds));

		return $hits;
	}

	/**
	 * Returns how many hits there have been in the current period, without
	 * counting a new one.
	 *
	 * @param   string  $key      what is being limited, and for whom
	 * @param   int     $seconds  length of the period
	 * @return  int
	 */
	public static function hits(string $key, int $seconds): int
	{
		$hits = Kohana::cache(Throttle::_cache_name($key, $seconds), null, max(1, $seconds));

		return is_numeric($hits) ? (int) $hits : 0;
	}

	/**
	 * Forgets the hits of the current period, for example after a
	 * successful login.
	 *
	 * @param   string  $key      what is being limited, and for whom
	 * @param   int     $seconds  length of the period
	 * @return  void
	 */
	public static function reset(string $key, int $seconds): void
	{
		Kohana::cache(Throttle::_cache_name($key, $seconds), 0, max(1, $seconds));
	}

	/**
	 * Returns the number of seconds until the current period ends.
	 *
	 * @param   int  $seconds  length of the period
	 * @return  int  at least 1
	 */
	public static function retry_after(int $seconds): int
	{
		$seconds = max(1, $seconds);

		return $seconds - (time() % $seconds);
	}

	/**
	 * Builds the name under which the counter of the current period is stored.
	 *
	 * @param   string  $key      what is being limited, and for whom
	 * @param   int     $seconds  length of the period
	 * @return  string
	 */
	protected static function _cache_name(string $key, int $seconds): string
	{
		$seconds = max(1, $seconds);

		// Periods are numbered: the number changes when a new period starts
		return 'Throttle:'.$key.':'.$seconds.':'.intdiv(time(), $seconds);
	}

}
