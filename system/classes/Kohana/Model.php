<?php
/**
 * Model base class. All models should extend this class.
 *
 * @package    Kohana
 * @category   Models
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Model {

	/**
	 * Create a new model instance.
	 *
	 *     $model = Model::factory($name);
	 *
	 * @param   string  $name   model name
	 * @return  Model
	 */
	public static function factory(string $name): Model
	{
		/** @var class-string<Model> $class  the model class */
		$class = 'Model_'.$name;

		return new $class;
	}

}
