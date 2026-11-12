<?php
/**
 * Documentation of one property of a class.
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Kodoc_Property extends Kodoc {

	/**
	 * @var  ReflectionProperty  the property itself
	 */
	public ReflectionProperty $property;

	/**
	 * @var  string  HTML of the modifiers (public, static, ...)
	 */
	public string $modifiers = '';

	/**
	 * @var  ?string  type: the one declared in the code, or else the one of the comment
	 */
	public ?string $type = null;

	/**
	 * @var  string  description of the property, as HTML
	 */
	public string $description = '';

	/**
	 * @var  ?string  HTML of the current value of a static property
	 */
	public ?string $value = null;

	/**
	 * @var  string  HTML of the default value
	 */
	public string $default;

	/**
	 * Reads a property.
	 *
	 * @param   class-string  $class     name of the class
	 * @param   string  $property  name of the property
	 * @param   mixed   $default   default value of the property
	 */
	public function __construct(string $class, string $property, mixed $default = null)
	{
		$this->property = new ReflectionProperty($class, $property);

		[$this->description, $tags] = Kodoc::parse($this->property->getDocComment());

		if ($modifiers = Reflection::getModifierNames($this->property->getModifiers()))
		{
			$this->modifiers = '<small>'.implode(' ', $modifiers).'</small> ';
		}

		if ($this->property->hasType())
		{
			$this->type = (string) $this->property->getType();
		}

		// "@var type description"
		if (isset($tags['var']) AND preg_match('/^('.Kodoc::TYPE.'*)(?:\s+(.+))?$/s', $tags['var'][0], $matches))
		{
			$this->type ??= $matches[1];

			if (isset($matches[2]))
			{
				$this->description = Kodoc_Markdown::markdown($matches[2]);
			}
		}

		// A value named like a secret (a salt, a key) is not shown
		$this->default = Debug::is_sensitive($property) ? '<pre>***</pre>' : (string) Debug::vars($default);

		if ($this->property->isStatic() AND $this->property->isInitialized())
		{
			$value = $this->property->getValue();

			$this->value = match (true)
			{
				Debug::is_sensitive($property) => '<pre>***</pre>',
				is_object($value) => '<pre>object '.get_class($value).'()</pre>',
				default => Debug::vars($value),
			};
		}
	}

}
