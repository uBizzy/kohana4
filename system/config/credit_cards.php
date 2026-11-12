<?php
/**
 * Credit card validation configuration, consumed by Valid::credit_card().
 *
 * Options for each card type:
 *  length - Allowed card number lengths, comma separated
 *  prefix - Leading digits the card must match, as a regex fragment
 *  luhn   - Whether to verify the number with the Luhn algorithm
 *
 * NOTE: BIN ranges change. Treat this as a format check only — it proves a
 * number is well-formed, never that a card exists or has funds.
 */
return [
	'default' => [
		'length' => '12,13,14,15,16,17,18,19',
		'prefix' => '',
		'luhn'   => true,
	],
	'american express' => [
		'length' => '15',
		'prefix' => '3[47]',
		'luhn'   => true,
	],
	'diners club' => [
		'length' => '14,16,19',
		'prefix' => '3(?:0[0-5]|[689])',
		'luhn'   => true,
	],
	'discover' => [
		'length' => '16,19',
		'prefix' => '6(?:011|5[0-9]{2}|4[4-9][0-9]|22[1-9])',
		'luhn'   => true,
	],
	'jcb' => [
		'length' => '15,16,19',
		// 3528-3589 (modern), plus legacy 15-digit 1800/2131 ranges.
		'prefix' => '35(?:2[89]|[3-8][0-9])|1800|2131',
		'luhn'   => true,
	],
	'maestro' => [
		'length' => '12,13,14,15,16,17,18,19',
		'prefix' => '5(?:018|0[23]|[68])|6(?:39|7)|600|69',
		'luhn'   => true,
	],
	'mastercard' => [
		// 51-55 (legacy) and 2221-2720 (issued since 2017).
		'length' => '16',
		'prefix' => '5[1-5]|2(?:22[1-9]|2[3-9][0-9]|[3-6][0-9]{2}|7[01][0-9]|720)',
		'luhn'   => true,
	],
	'unionpay' => [
		// UnionPay does not mandate Luhn: many valid cards fail it.
		'length' => '16,17,18,19',
		'prefix' => '62',
		'luhn'   => false,
	],
	'visa' => [
		'length' => '13,16,19',
		'prefix' => '4',
		'luhn'   => true,
	],
];
