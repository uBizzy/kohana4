<?php
/**
 * Documentation of one method of a class.
 *
 * The types of the parameters and of the returned value come from the
 * code. The comment adds the descriptions (and the types, where the code
 * declares none).
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Kodoc_Method extends Kodoc {

	/**
	 * @var  ReflectionMethod  the method itself
	 */
	public ReflectionMethod $method;

	/**
	 * @var  ReflectionClass<object>  the class that declares the method
	 */
	public ReflectionClass $class;

	/**
	 * @var  string  HTML of the modifiers (public, static, ...)
	 */
	public string $modifiers = '';

	/**
	 * @var  string  description of the method, as HTML
	 */
	public string $description = '';

	/**
	 * @var  array<int, Kodoc_Method_Param>  parameters: list of [Kodoc_Method_Param]
	 */
	public array $params = [];

	/**
	 * @var  array<int, array<int, string>>  what the method returns: list of [type, description]
	 */
	public array $return = [];

	/**
	 * @var  array<string, mixed>  the other tags of the comment, as HTML
	 */
	public array $tags = [];

	/**
	 * @var  string|false  source code of the method
	 */
	public string|false $source = false;

	/**
	 * Reads a method.
	 *
	 * @param   string  $class   name of the class
	 * @param   string  $method  name of the method
	 * @throws  ReflectionException  when the method does not exist
	 */
	public function __construct(string $class, string $method)
	{
		$this->method = new ReflectionMethod($class, $method);
		$this->class = $this->method->getDeclaringClass();

		if ($modifiers = Reflection::getModifierNames($this->method->getModifiers()))
		{
			$this->modifiers = '<small>'.implode(' ', $modifiers).'</small> ';
		}

		// The comment of the method, or of the nearest parent method that has one
		$comment = false;

		for ($parent = $this->class; $parent AND ! $comment; $parent = $parent->getParentClass())
		{
			if ($parent->hasMethod($method))
			{
				$comment = $parent->getMethod($method)->getDocComment();
			}
		}

		[$this->description, $tags] = Kodoc::parse($comment);

		$this->source = Kodoc::source($this->method->getFileName(), (int) $this->method->getStartLine(), (int) $this->method->getEndLine());

		foreach ($this->method->getParameters() as $i => $reflection)
		{
			$param = new Kodoc_Method_Param([$this->method->class, $this->method->name], $i);

			// "type $name description" of the comment; the name is optional
			if (isset($tags['param'][$i]) AND preg_match('/^('.Kodoc::TYPE.'+)(?:\s+(?:&?\.{0,3}\$'.$param->name.'\b\s*)?(.+))?$/s', strip_tags($tags['param'][$i]), $matches))
			{
				// The type declared in the code is the true one
				$param->type ??= $matches[1];
				$param->description = ucfirst($matches[2] ?? '');
			}

			$this->params[] = $param;
		}

		// The type declared in the code, with the description of the first @return
		$declared = $this->method->hasReturnType() ? (string) $this->method->getReturnType() : null;

		foreach ($tags['return'] ?? [] as $n => $return)
		{
			preg_match('/^('.Kodoc::TYPE.'*)(?:\s+(.+))?$/s', strip_tags($return), $matches);

			$this->return[] = [($n === 0 AND $declared !== null) ? $declared : ($matches[1] ?? ''), $matches[2] ?? ''];
		}

		if ( ! $this->return AND $declared !== null AND $declared !== 'void')
		{
			$this->return[] = [$declared, ''];
		}

		unset($tags['param'], $tags['return']);

		$this->tags = $tags;
	}

	/**
	 * Returns the parameters as they are written in a call:
	 * `$key, $default = null`, with optional ones in brackets.
	 *
	 * @return  string  HTML
	 */
	public function params_short(): string
	{
		$out = '';
		$optional = 0;

		foreach ($this->params as $i => $param)
		{
			if ($param->optional)
			{
				$out .= ($i ? ' [, ' : '[ ').$param;
				$optional++;
			}
			else
			{
				$out .= ($i ? ', ' : '').$param;
			}
		}

		return $out.str_repeat(']', $optional).' ';
	}

}
