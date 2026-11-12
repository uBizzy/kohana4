<?php
/**
 * Documentation of one parameter of a method.
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Kodoc_Method_Param extends Kodoc {

	/**
	 * @var  ReflectionParameter  the parameter itself
	 */
	public ReflectionParameter $param;

	/**
	 * @var  string  name of the parameter, without $
	 */
	public string $name;

	/**
	 * @var  ?string  type: the one declared in the code, or else the one of the comment
	 */
	public ?string $type = null;

	/**
	 * @var  ?string  HTML of the default value; null when there is none
	 */
	public ?string $default = null;

	/**
	 * @var  string  description of the parameter
	 */
	public string $description = '';

	/**
	 * @var  bool  is the parameter passed by reference?
	 */
	public bool $reference = false;

	/**
	 * @var  bool  can the parameter be left out?
	 */
	public bool $optional = false;

	/**
	 * @var  bool  does the parameter take all remaining arguments (`...$values`)?
	 */
	public bool $variadic = false;

	/**
	 * Reads a parameter.
	 *
	 * @param   array<int, string>       $method  [class name, method name]
	 * @param   int|string  $param   position or name of the parameter
	 */
	public function __construct(array $method, int|string $param)
	{
		$this->param = new ReflectionParameter($method, $param);
		$this->name = $this->param->name;
		$this->reference = $this->param->isPassedByReference();
		$this->optional = $this->param->isOptional();
		$this->variadic = $this->param->isVariadic();

		if ($this->param->hasType())
		{
			$this->type = (string) $this->param->getType();
		}

		if ($this->param->isDefaultValueAvailable())
		{
			$this->default = Debug::dump($this->param->getDefaultValue());
		}
	}

	/**
	 * Returns the parameter as it is written in a declaration.
	 *
	 * @return  string  HTML
	 */
	public function __toString(): string
	{
		$display = ($this->type !== null) ? '<small>'.HTML::chars($this->type).'</small> ' : '';

		$name = ($this->reference ? '&amp;' : '').($this->variadic ? '...' : '').'$'.$this->name;

		$display .= ($this->description !== '')
			? '<span class="param" title="'.HTML::chars(preg_replace('/\s+/', ' ', $this->description)).'">'.$name.'</span> '
			: $name.' ';

		if ($this->default !== null)
		{
			$display .= '<small>= '.$this->default.'</small> ';
		}

		return $display;
	}

}
