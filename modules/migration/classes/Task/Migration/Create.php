<?php
/**
 * Writes the file of a new, empty migration in application/migrations.
 *
 *     ./minion migration:create --name=create_articles
 *
 * Options:
 *  --name  what the migration does: lower case letters, digits and "_" (needed)
 *
 * @package    Kohana/Migration
 * @category   Tasks
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Task_Migration_Create extends Minion_Task {

	/**
	 * @var  array<int|string, mixed>
	 */
	protected array $_options = [
		'name' => null,
	];

	/**
	 * Adds the rules for the "name" option.
	 *
	 * @param   Validation  $validation
	 * @return  Validation
	 */
	public function build_validation(Validation $validation): Validation
	{
		return parent::build_validation($validation)
			->rule('name', 'not_empty')
			->rule('name', 'regex', [':value', '/^[a-z0-9_]+$/']);
	}

	/**
	 * @param   array<int|string, mixed>  $params  options of the command line
	 * @return  mixed
	 */
	protected function _execute(array $params): mixed
	{
		$file = (new Migration_Runner)->create(is_string($params['name']) ? $params['name'] : '');

		Minion_CLI::success('Created '.Debug::path($file));

		return null;
	}

}
