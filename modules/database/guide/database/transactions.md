# Transactions

A transaction makes several queries one step: either all of them happen, or none. Use one whenever a half-done change would leave the data wrong.

## The simple way

Give `transaction()` a function. It is committed when the function ends, and rolled back when it throws.

```php
$db = Database::instance();

$order_id = $db->transaction(function (Database $db) use ($cart, $user) {

	[$order_id] = DB::insert('orders', ['user_id', 'total'])
		->values([$user->id, $cart->total()])
		->execute($db);

	foreach ($cart->lines() as $line)
	{
		DB::insert('order_lines', ['order_id', 'product_id', 'quantity'])
			->values([$order_id, $line->product_id, $line->quantity])
			->execute($db);

		// Throws when there is not enough stock: everything above is undone
		$this->take_from_stock($db, $line);
	}

	return $order_id;
});
```

- What the function returns is returned by `transaction()`.
- An exception undoes every query of the function, and is thrown on to you.

```php
try
{
	$db->transaction(function (Database $db) {
		// ...
	});
}
catch (Shop_Exception $e)
{
	// Nothing was changed
	$error = $e->getMessage();
}
```

## By hand

```php
$db = Database::instance();

$db->begin();

try
{
	DB::update('accounts')->set(['balance' => DB::expr('balance - :n', [':n' => 50])])->where('id', '=', 1)->execute($db);
	DB::update('accounts')->set(['balance' => DB::expr('balance + :n', [':n' => 50])])->where('id', '=', 2)->execute($db);

	$db->commit();
}
catch (Throwable $e)
{
	$db->rollback();

	throw $e;
}
```

## Isolation level

How much a transaction sees of other transactions running at the same time.

```php
$db->transaction(function (Database $db) {
	// ...
}, 'SERIALIZABLE');

// Or
$db->begin('READ COMMITTED');
```

The levels are `READ UNCOMMITTED`, `READ COMMITTED`, `REPEATABLE READ` and `SERIALIZABLE`.

## Things to know

- The tables must support transactions. In MySQL and MariaDB that is InnoDB, not MyISAM.
- Statements that change tables (`CREATE`, `ALTER`, `DROP`) end a transaction in MySQL: they cannot be undone.
- Keep transactions short. Do not call other services or send mail inside one: other requests wait for the rows you hold.
- Run all queries of the transaction on the same connection (`->execute($db)`).
- With [ORM](../orm), saving several models inside `transaction()` works the same way.
