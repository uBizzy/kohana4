<?php
/**
 * Documentation of one class: its description, tags, constants,
 * properties and methods.
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Kodoc_Class extends Kodoc {

	/**
	 * @var  ReflectionClass<object>  the class itself
	 */
	public ReflectionClass $class;

	/**
	 * @var  string  HTML of the modifiers (abstract, final)
	 */
	public string $modifiers = '';

	/**
	 * @var  string  description of the class, as written in its comment
	 */
	public string $description = '';

	/**
	 * @var  array<string, array<int, string>>  tags of the comment, as written
	 */
	public array $tags = [];

	/**
	 * @var  array<string, mixed>  constants of the class: name => value
	 */
	public array $constants = [];

	/**
	 * @var  array<int, ReflectionClass<object>>  parent classes (or, for an interface, the interfaces it extends)
	 */
	public array $parents = [];

	/**
	 * Reads a class.
	 *
	 * @param   class-string  $class  name of the class
	 * @throws  ReflectionException  when the class does not exist
	 */
	public function __construct(string $class)
	{
		$this->class = new ReflectionClass($class);

		if ($modifiers = Reflection::getModifierNames($this->class->getModifiers()))
		{
			$this->modifiers = '<small>'.implode(' ', $modifiers).'</small> ';
		}

		$this->constants = $this->class->getConstants();

		if ($this->class->isInterface())
		{
			$this->parents = array_values($this->class->getInterfaces());
		}
		else
		{
			for ($parent = $this->class->getParentClass(); $parent; $parent = $parent->getParentClass())
			{
				$this->parents[] = $parent;
			}
		}

		// The comment of the class, or of the nearest parent that has one
		$comment = $this->class->getDocComment();

		foreach ($this->parents as $parent)
		{
			if ($comment)
				break;

			$comment = $parent->getDocComment();
		}

		[$this->description, $this->tags] = Kodoc::parse($comment, false);
	}

	/**
	 * Returns the constants with their values as HTML.
	 *
	 * @return  array<string, string>  name => HTML
	 */
	public function constants(): array
	{
		return array_map(fn ($value) => (string) Debug::vars($value), $this->constants);
	}

	/**
	 * Returns the description of the class as HTML.
	 *
	 * @return  string
	 */
	public function description(): string
	{
		return Kodoc_Markdown::markdown($this->description);
	}

	/**
	 * Returns the properties of the class: public first, then protected,
	 * then private, each group by name.
	 *
	 * @return  array<int, Kodoc_Property>  list of [Kodoc_Property]
	 */
	public function properties(): array
	{
		$properties = $this->class->getProperties();
		$defaults = $this->class->getDefaultProperties();

		usort($properties, fn ($a, $b) => [$this->_visibility($a), $a->name] <=> [$this->_visibility($b), $b->name]);

		return array_map(fn ($property) => new Kodoc_Property($this->class->name, $property->name, $defaults[$property->name] ?? null), $properties);
	}

	/**
	 * Returns the methods of the class: public first, then protected,
	 * then private; in each group the methods of the class itself before
	 * those of its parents, by name.
	 *
	 * @return  array<int, Kodoc_Method>  list of [Kodoc_Method]
	 */
	public function methods(): array
	{
		$methods = $this->class->getMethods();

		usort($methods, fn ($a, $b) => [$this->_visibility($a), $this->_distance($a), $a->name] <=> [$this->_visibility($b), $this->_distance($b), $b->name]);

		return array_map(fn ($method) => new Kodoc_Method($this->class->name, $method->name), $methods);
	}

	/**
	 * Returns the tags of the class as HTML.
	 *
	 * @return  array<string, array<int, string>>  tag name => list of HTML
	 */
	public function tags(): array
	{
		$result = [];

		foreach ($this->tags as $name => $texts)
		{
			$result[$name] = array_map(fn ($text) => Kodoc::format_tag($name, $text), $texts);
		}

		return $result;
	}

	/**
	 * Sorting: 0 for public, 1 for protected, 2 for private.
	 *
	 * @param   ReflectionMethod|ReflectionProperty  $member  method or property
	 * @return  int
	 */
	protected function _visibility(ReflectionMethod|ReflectionProperty $member): int
	{
		return $member->isPublic() ? 0 : ($member->isProtected() ? 1 : 2);
	}

	/**
	 * Sorting: how many classes up a method is declared. Arr and its
	 * base class Kohana_Arr count as the same class.
	 *
	 * @param   ReflectionMethod  $method  the method
	 * @return  int
	 */
	protected function _distance(ReflectionMethod $method): int
	{
		$declares = $method->getDeclaringClass()->name;

		if ($declares === $this->class->name OR Kodoc::is_transparent($declares) === $this->class->name)
			return 0;

		foreach ($this->parents as $n => $parent)
		{
			if ($parent->name === $declares)
				return $n + 1;
		}

		// Declared in an interface or a trait
		return count($this->parents) + 1;
	}

}
