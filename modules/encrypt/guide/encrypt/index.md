# Encrypt

The Encrypt module turns a text into one that only your application can read back. Use it for data you must be able to **read again**: a token of another service, an address, a note.

```php
// application/bootstrap.php
Kohana::modules([
	'encrypt' => MODPATH.'encrypt',
]);
```

[!!] Do not encrypt passwords. A password is **hashed**, so that nobody can read it back: see the [Auth module](../auth/passwords).

## Setting up

Encryption needs a secret key. Make one:

```bash
php -r 'echo bin2hex(random_bytes(16)), "\n";'
```

```php
// application/config/encrypt.php
return [
	'default' => [
		'type'   => 'openssl',
		'key'    => 'the 32 characters printed above',
		'cipher' => Encrypt_Engine_Openssl::AES_256_CBC,
	],
];
```

- The key must be exactly 32 bytes for AES-256 (16 for AES-128). Another length throws an exception.
- Keep this file out of version control, and use a different key on every installation.
- **Lose the key and the data is lost.** Keep a copy somewhere safe.

## Using it

```php
$encrypt = Encrypt::instance();

$secret = $encrypt->encode('4111 1111 1111 1111');
// "eyJpdiI6Ii4uLiIsInZhbHVlIjoiLi4uIiwibWFjIjoiLi4uIn0="

$plain = $encrypt->decode($secret);
// "4111 1111 1111 1111"
```

- The same text gives a **different result every time**, so two rows with the same value do not look the same. You cannot search a column by its encrypted value.
- `decode()` returns `null` for anything it cannot read: text that was changed, made with another key, or not encrypted at all. It never throws.

```php
$token = $encrypt->decode($row['api_token']);

if ($token === null)
{
	// Damaged, or from another key
}
```

## In a model

```php
class Model_Integration extends ORM {

	/**
	 * The token is stored encrypted and used in the clear.
	 */
	public function set_token(string $token): static
	{
		return $this->set('token', Encrypt::instance()->encode($token));
	}

	public function token(): ?string
	{
		return Encrypt::instance()->decode((string) $this->get('token'));
	}
}
```

The column must be long enough: the encrypted text is about twice the length of the original, plus 100 characters.

## Several keys

Each key of the config is a group.

```php
return [
	'default'  => ['type' => 'openssl', 'key' => '...', 'cipher' => Encrypt_Engine_Openssl::AES_256_CBC],
	'payments' => ['type' => 'openssl', 'key' => '...', 'cipher' => Encrypt_Engine_Openssl::AES_256_CBC],
];
```

```php
$encrypt = Encrypt::instance('payments');
```

## Engines

| Engine | `type` | Ciphers | Needs |
|---|---|---|---|
| OpenSSL | `openssl` | `Encrypt_Engine_Openssl::AES_256_CBC`, `AES_128_CBC` | the `openssl` extension |
| Sodium | `sodium` | `Encrypt_Engine_Sodium::AES_256_GCM`, `CHACHA_POLY`, `CHACHA_POLY_IETF`, `XCHACHA_POLY_IETF` | the `sodium` extension |

```php
// Sodium: the key is 32 raw bytes
'default' => [
	'type'   => 'sodium',
	'key'    => base64_decode('...44 characters made with base64_encode(random_bytes(32))...'),
	'cipher' => Encrypt_Engine_Sodium::XCHACHA_POLY_IETF,
],
```

Both engines also sign the result, so a text that was changed is refused, not decoded into nonsense. Data encrypted with one engine or key cannot be read with another.

## Encrypted sessions and cookies

```php
// application/config/session.php
return [
	'cookie' => [
		'encrypted' => true,        // the group "default", or the name of another group
	],
];
```

See [Sessions](../kohana/sessions).

## Changing the key

There is no automatic way. To change a key, add the new one as a second group, read each value with the old group and write it with the new one, then remove the old group.

```php
$old = Encrypt::instance('old');
$new = Encrypt::instance('default');

foreach (ORM::factory('Integration')->find_all() as $row)
{
	$plain = $old->decode((string) $row->get('token'));

	if ($plain !== null)
	{
		$row->set('token', $new->encode($plain))->save();
	}
}
```
