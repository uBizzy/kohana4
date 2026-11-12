<?php
/**
 * Default auth role
 *
 * @package    Kohana/Auth
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Model_Auth_Role extends ORM {

	/**
	 * @var  array<string, array<string, string|bool|null>>  Relationships
	 */
	protected array $_has_many = [
		'users' => ['model' => 'User','through' => 'roles_users'],
	];

	/**
	 * @return  array<string, array<int, array<int, mixed>>>
	 */
	public function rules(): array
	{
		return [
			'name' => [
				['not_empty'],
				['min_length', [':value', 4]],
				['max_length', [':value', 32]],
			],
			'description' => [
				['max_length', [':value', 255]],
			]
		];
	}

} // End Auth Role Model
