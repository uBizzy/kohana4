<?php

/**
 * @package    Kohana/ORM
 * 
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_ORM_Behavior_LocalBehavior extends ORM_Behavior {

	/**
	 * Callback to execute
	 * @var callable
	 */
	protected mixed $_callback;

	/**
	 * Constructs a behavior object
	 *
	 * @param   callable $callback Callback to execute
	 */
	protected function __construct(mixed $callback)
  {
	$this->_callback = $callback;
  }

	/**
	 * Constructs a new model and loads a record if given
	 *
   * @param   ORM   $model The model
	 * @param   mixed $id    Parameter for find or object to load
	 */
	public function on_construct(ORM $model, mixed $id): bool
	{
		$params = ['construct', $id];
		$result = call_user_func_array($this->_callback, $params);

		if (is_bool($result))
			return $result;

		// Continue loading the record
		return true;
	}

  /**
   * The model is updated
   */
	public function on_update(ORM $model): void
  {
		$params = ['update'];
		call_user_func_array($this->_callback, $params);
  }

  /**
   * A new model is created
   *
   * @param   ORM   $model The model
   */
	public function on_create(ORM $model): void
  {
		$params = ['create'];
		call_user_func_array($this->_callback, $params);
  }
}
