<?php
/**
 * @package        Kohana/ORM
 * even Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license        https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class ORM_Behavior_Guid extends ORM_Behavior {

	/**
	 * Table column for GUID value
	 * @var string
	 */
	protected string $_guid_column = 'guid';

	/**
	 * Load a record by its GUID when the model is constructed with one.
	 * Set "guid_only" to false to switch this off.
	 * @var bool
	 */
	protected bool $_guid_only = true;

	/**
	 * Verify GUID
	 * @var bool
	 */
	protected bool $_guid_verify = false;

	/**
	 * Constructs a behavior object
	 *
	 * @param   array{column?: string, guid_only?: bool, verify?: bool} $config Configuration parameters
	 */
	protected function __construct(mixed $config)
	{
		parent::__construct($config);

		$this->_guid_column = $config['column'] ?? $this->_guid_column;
		$this->_guid_only = $config['guid_only'] ?? $this->_guid_only;
		$this->_guid_verify = $config['verify'] ?? $this->_guid_verify;
	}

	/**
	 * Constructs a new model and loads a record if given
	 *
	 * @param   ORM   $model The model
	 * @param   mixed $id    Parameter for find or object to load
	 *
	 * @return bool
	 * @throws Kohana_Exception
	 */
	public function on_construct(ORM $model, mixed $id): bool
	{
		// A numeric id is a primary key; anything else must be a UUID
		if ($this->_guid_only && is_string($id) && ! ctype_digit($id)) {
			if ( ! UUID:: valid($id)) {
				throw new Kohana_Exception('Invalid UUID: :id', [':id' => $id]);
			}
			$model->where($this->_guid_column, '=', $id)->find();

			// Prevent further record loading
			return false;
		}

		return true;
	}

	/**
	 * The model is updated, add a guid value if empty
	 *
	 * @param   ORM $model The model
	 *
	 * @throws Exception
	 */
	public function on_update(ORM $model): void
	{
		$this->create_guid($model);
	}

	/**
	 * Gives the model a GUID, when it has none: a record keeps the one
	 * it got. With "verify" on, the table is asked whether the new GUID
	 * is in use, however unlikely that is.
	 *
	 * @param   ORM  $model  Model to generate GUID for
	 * @return  void
	 */
	protected function create_guid(ORM $model): void
	{
		if ( ! empty($model->get($this->_guid_column)))
			return;

		do
		{
			$guid = UUID::v4();
		}
		while ($this->_guid_verify AND $this->taken($model, $guid));

		$model->set($this->_guid_column, $guid);
	}

	/**
	 * Tells whether a record of the model has this GUID.
	 *
	 * @param   ORM     $model  The model
	 * @param   string  $guid   The GUID to look for
	 * @return  bool
	 */
	protected function taken(ORM $model, string $guid): bool
	{
		// In the database of the model, which need not be the default one
		return DB::select($model->primary_key())
			->from($model->table_name())
			->where($this->_guid_column, '=', $guid)
			->limit(1)
			->execute($model->db())
			->count() > 0;
	}

	/**
	 * A new model is created, add a guid value
	 *
	 * @param   ORM $model The model
	 * @throws	Exception
	 */
	public function on_create(ORM $model): void
	{
		$this->create_guid($model);
	}
}
