<?php

/**
 * @package    Kohana/ORM
 *
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class ORM_Behavior_Slug extends ORM_Behavior {

	/**
	 * Column from which the slug is built
	 * @var string
	 */
	protected string $_slug_source_column = 'name';

	/**
	 * Column in which the slug is stored
	 * @var string
	 */
	protected string $_slug_column = 'slug';

	/**
	 * Constructs a behavior object
	 *
	 * @param   array{source?: string, column?: string}  $config  Configuration: "source" and "column" names
	 */
	protected function __construct(mixed $config)
	{
		parent::__construct($config);

		$this->_slug_source_column = $config['source'] ?? $this->_slug_source_column;
		$this->_slug_column = $config['column'] ?? $this->_slug_column;
	}

	/**
	 * Constructs a new model and loads a record if given
	 *
	 * @param   ORM    $model  The model
	 * @param   mixed  $id     Parameter for find or object to load
	 * @return  bool   false when the model was loaded here by its slug
	 */
	public function on_construct(ORM $model, mixed $id): bool
	{
		// A numeric id is a primary key; any other text is a slug
		if (is_string($id) AND ! ctype_digit($id))
		{
			$model->where($this->_slug_column, '=', $id)->find();

			// Prevent further record loading
			return false;
		}

		return true;
	}

	/**
	 * The model is updated, add a slug value if empty
	 *
	 * @param   ORM  $model  The model
	 * @return  void
	 */
	public function on_update(ORM $model): void
	{
		$this->create_slug($model);
	}

	/**
	 * A new model is created, add a slug value
	 *
	 * @param   ORM  $model  The model
	 * @return  void
	 */
	public function on_create(ORM $model): void
	{
		$this->create_slug($model);
	}

	/**
	 * Builds the slug from the source column and stores it in the model.
	 * If another record already uses the slug, a number is appended:
	 * "my-title", "my-title-0", "my-title-1", ...
	 *
	 * @param   ORM  $model  The model
	 * @return  void
	 */
	protected function create_slug(ORM $model): void
	{
		$source = $model->get($this->_slug_source_column);
		$base   = $this->slugify(is_scalar($source) ? (string) $source : '');

		if ($base === '')
		{
			// No source, or one without a letter or digit: the name of the model
			$base = $this->slugify($model->object_name(), false);
		}

		// The record has this slug already, or a numbered one of it
		$current = $model->get($this->_slug_column);

		if (is_string($current) AND preg_match('/^'.preg_quote($base, '/').'(-\d+)?$/', $current) AND ! $this->taken($model, $current))
			return;

		$slug = $base;

		for ($index = 0; $this->taken($model, $slug); $index++)
		{
			// Find the first unused numbered slug
			$slug = $base.'-'.$index;
		}

		$model->set($this->_slug_column, $slug);
	}

	/**
	 * Tells whether another record of the model has this slug.
	 *
	 * @param   ORM     $model  The model
	 * @param   string  $slug   The slug to look for
	 * @return  bool
	 */
	protected function taken(ORM $model, string $slug): bool
	{
		$query = DB::select($model->primary_key())
			->from($model->table_name())
			->where($this->_slug_column, '=', $slug)
			->limit(1);

		if ($model->pk() !== null)
		{
			// A new record has no key yet; a saved one does not count itself
			$query->where($model->primary_key(), '!=', $model->pk());
		}

		// In the database of the model, which need not be the default one
		return $query->execute($model->db())->count() > 0;
	}

	/**
	 * Create a safe pathname
	 *
	 *     "Ünïcode Title!" becomes "unicode-title"
	 *
	 * @param   string  $text    The text to transform
	 * @param   bool    $strict  If true, dots are removed as well
	 * @return  string
	 */
	protected function slugify(string $text, bool $strict = true): string
	{
		$text = $this->remove_accents($text);

		// Replace non letter or digits by -
		$text = (string) preg_replace('~[^\\pL\d.]+~u', '-', $text);

		// Trim
		$text = trim($text, '-');

		// Lowercase
		$text = strtolower($text);

		// Remove unwanted characters
		$text = (string) preg_replace('~[^-\w.]+~', '', $text);

		if ($strict)
		{
			$text = str_replace('.', '', $text);
		}

		return $text;
	}

	/**
	 * Checks to see if a string is utf8 encoded.
	 *
	 * @param   string  $str  The string to be checked
	 * @return  bool
	 */
	public function seems_utf8(string $str): bool
	{
		return mb_check_encoding($str, 'UTF-8');
	}

	/**
	 * Converts all accent characters to ASCII characters.
	 *
	 * If there are no accent characters, then the string given is just returned.
	 *
	 * @param   string  $string  Text that might have accent characters
	 * @return  string  Filtered string with replaced "nice" characters.
	 * @uses    UTF8::transliterate_to_ascii
	 */
	public function remove_accents(string $string): string
	{
		if (UTF8::is_ascii($string))
			return $string;

		// Text that is not valid UTF-8 is read as ISO-8859-1 (Latin-1)
		if ( ! $this->seems_utf8($string))
		{
			$string = mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
		}

		return UTF8::transliterate_to_ascii($string);
	}

}
