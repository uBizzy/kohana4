<?php

/**
 * @package    Kohana/ORM
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_ORM_Behavior {

	/**
	 * Settings of the behavior
	 * @var mixed
	 */
	protected mixed $_config = null;

	/**
	 * Creates and returns a new ORM behavior.
	 *
	 * @chainable
	 * @param   string|int  $behavior  name of the behavior, or a number when $config is a callback
	 * @param   mixed       $config    settings of the behavior, or a callback
	 * @return  ORM_Behavior
	 */
	public static function factory(string|int $behavior, mixed $config = null): ORM_Behavior
	{
		if ( ! is_string($behavior) AND is_array($config))
		{
			if ( ! is_callable($config))
				throw new Kohana_Exception('Behavior cannot be created: function does not exists');

			// This is either a callback as an array or a lambda
			return new ORM_Behavior_LocalBehavior($config);
		}

		/** @var class-string<ORM_Behavior> $behavior_name  the behavior class */
		$behavior_name = 'ORM_Behavior_'.ucfirst( (string) $behavior);

		return new $behavior_name($config);
	}

	protected function __construct(mixed $config)
	{
		$this->_config = $config;
	}

	public function on_construct(ORM $model, mixed $id): bool { return true; }
	public function on_create(ORM $model): void { }
	public function on_update(ORM $model): void { }
}
