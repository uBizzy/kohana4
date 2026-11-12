<?php
/**
 * Default auth user toke
 *
 * @package    Kohana/Auth
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 *
 * @property  int|string  $id
 * @property  int|string  $user_id
 * @property  string      $user_agent  hash of the browser name
 * @property  string      $token
 * @property  int|string  $created
 * @property  int|string  $expires
 * @property  Model_User  $user
 */
class Model_Auth_User_Token extends ORM {

	/**
	 * @var  array<string, array<string, string>>  Relationships
	 */
	protected array $_belongs_to = [
		'user' => ['model' => 'User'],
	];

	/**
	 * @var  array{column: string, format: string|true}|null
	 */
	protected ?array $_created_column = [
		'column' => 'created',
		'format' => true,
	];

	/**
	 * Handles garbage collection and deleting of expired objects.
	 *
	 * @return  void
	 */
	public function __construct(mixed $id = null)
	{
		parent::__construct($id);

		if (random_int(1, 100) === 1)
		{
			// Do garbage collection
			$this->delete_expired();
		}

		if ($this->expires < time() AND $this->_loaded)
		{
			// This object has expired
			$this->delete();
		}
	}

	/**
	 * Deletes all expired tokens.
	 *
	 * @return  static
	 */
	public function delete_expired(): static
	{
		// Delete all expired tokens
		DB::delete($this->_table_name)
			->where('expires', '<', time())
			->execute($this->_db);

		return $this;
	}

	public function create(?Validation $validation = null): static
	{
		$this->token = $this->create_token();

		return parent::create($validation);
	}

	/**
	 * Saves the token with a new value. A token is updated each time it is
	 * used for an automatic login, so every token works only once: a copy
	 * of an old cookie is worthless.
	 *
	 * @param   Validation|null  $validation  extra validation
	 * @return  static
	 */
	public function update(?Validation $validation = null): static
	{
		$this->token = $this->create_token();

		return parent::update($validation);
	}

	/**
	 * Generates a new unpredictable token: 40 hexadecimal characters
	 * (160 random bits), so collisions are not a practical concern.
	 *
	 * @return  string
	 */
	protected function create_token(): string
	{
		return bin2hex(random_bytes(20));
	}

} // End Auth User Token Model
