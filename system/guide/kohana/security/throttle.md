# Rate Limiting

`Throttle` lets something happen only a number of times in a period: log in attempts, messages from a contact form, calls to an API.

```php
// At most 60 calls a minute for each client address.
// Beyond that the request is answered with "429 Too Many Requests"
Throttle::check('api:'.Request::$client_ip, 60, 60);
```

## The key

The first argument names **what** is limited and **for whom**. You choose it.

| Limit | Key |
|---|---|
| Per visitor | `'api:'.Request::$client_ip` |
| Per account | `'login:'.strtolower($username)` |
| Per logged-in user | `'export:'.$user->id` |
| For everybody together | `'newsletter'` |

## Two ways to use it

`Throttle::check()` stops the request by itself:

```php
public function before(): void
{
	parent::before();

	Throttle::check('api:'.Request::$client_ip, 60, 60);
}
```

The visitor gets status 429 and a `Retry-After` header that says how many seconds to wait.

`Throttle::allow()` only tells you, so you can answer in your own way:

```php
public function action_send(): void
{
	// 3 messages an hour from one address
	if ( ! Throttle::allow('contact:'.Request::$client_ip, 3, Date::HOUR))
	{
		$minutes = ceil(Throttle::retry_after(Date::HOUR) / 60);

		$this->response->body(View::factory('contact/wait', ['minutes' => $minutes]));
		return;
	}

	// ... send the message
}
```

Both count the attempt.

## Log in attempts

Count only the failures, and forget them after a success.

```php
public function action_login(): void
{
	$username = (string) $this->request->post('username');
	$key = 'login:'.strtolower($username);

	// 5 wrong passwords in 15 minutes
	if (Throttle::hits($key, 900) >= 5)
		throw HTTP_Exception::factory(429, 'Too many attempts. Try again in a few minutes.');

	if (Auth::instance()->login($username, (string) $this->request->post('password')))
	{
		Throttle::reset($key, 900);
		$this->redirect('account');
	}

	// Wrong password: count it
	Throttle::hit($key, 900);
}
```

Limit by account **and** by address when you can: one stops guessing a single password, the other stops trying one password on many accounts.

## The methods

| Method | Does |
|---|---|
| `Throttle::check($key, $max, $seconds)` | Counts, and throws a 429 error when over the limit |
| `Throttle::allow($key, $max, $seconds)` | Counts, and returns `false` when over the limit |
| `Throttle::hit($key, $seconds)` | Counts, and returns the new number |
| `Throttle::hits($key, $seconds)` | Returns the number, without counting |
| `Throttle::reset($key, $seconds)` | Starts from zero again |
| `Throttle::retry_after($seconds)` | Seconds until the current period ends |

## How it counts

- Time is cut into fixed periods: with 60 seconds, one per minute. Each period counts from zero.
- The counters are kept with `Kohana::cache()`: in the [Cache module](../../cache) when it is set up, otherwise in the cache files of the application.
- Under many requests at the same moment a few more than the limit can pass. It is a brake, not an exact meter.
- With several web servers, use a cache they share (Redis, Memcached), or each server counts for itself.
- Behind a proxy, set [`Request::$trusted_proxies`](../requests), or every visitor has the proxy's address and shares one counter.
