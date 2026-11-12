<?php
/**
 * Form helper class. Unless otherwise noted, all generated HTML will be made
 * safe using the [HTML::chars] method. This prevents against simple XSS
 * attacks that could otherwise be triggered by inserting HTML characters into
 * form fields.
 *
 * @package    Kohana
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Form {

	/**
	 * Generates an opening HTML form tag.
	 *
	 *     // Form will submit back to the current page using POST
	 *     echo Form::open();
	 *
	 *     // Form will submit to 'search' using GET
	 *     echo Form::open('search', array('method' => 'get'));
	 *
	 *     // When "file" inputs are present, you must include the "enctype"
	 *     echo Form::open(null, array('enctype' => 'multipart/form-data'));
	 *
	 * @param   Request|string|null   $action     form action, defaults to the current request URI, or [Request] class to use
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    Request
	 * @uses    URL::site
	 * @uses    HTML::attributes
	 */
	public static function open(Request|string|null $action = null, ?array $attributes = null): string
	{
		if ($action instanceof Request)
		{
			// Use the current URI
			$action = $action->uri();
		}

		if ( ! $action)
		{
			// Allow empty form actions (submits back to the current url).
			$action = '';
		}
		elseif (strpos($action, '://') === false AND strncmp($action, '//', 2))
		{
			// Make the URI absolute
			$action = URL::site($action);
		}

		// Add the form action to the attributes
		$attributes['action'] = $action;

		// Only accept the default character set
		$attributes['accept-charset'] = Kohana::$charset;

		if ( ! isset($attributes['method']))
		{
			// Use POST method
			$attributes['method'] = 'post';
		}

		return '<form'.HTML::attributes($attributes).'>';
	}

	/**
	 * Creates the closing form tag.
	 *
	 *     echo Form::close();
	 *
	 * @return  string
	 */
	public static function close(): string
	{
		return '</form>';
	}

	/**
	 * Creates a form input. If no type is specified, a "text" type input will
	 * be returned.
	 *
	 *     echo Form::input('username', $username);
	 *
	 * @param   string  $name       input name
	 * @param   string  $value      input value
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    HTML::attributes
	 */
	public static function input(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		// Set the input name
		$attributes['name'] = $name;

		// Set the input value
		$attributes['value'] = $value;

		if ( ! isset($attributes['type']))
		{
			// Default type is text
			$attributes['type'] = 'text';
		}

		return '<input'.HTML::attributes($attributes).' />';
	}

	/**
	 * Creates a hidden form input.
	 *
	 *     echo Form::hidden('csrf', $token);
	 *
	 * @param   string  $name       input name
	 * @param   string  $value      input value
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function hidden(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'hidden';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a hidden form input holding the CSRF token of the current
	 * session. Check it on submit with [Security::check].
	 *
	 *     echo Form::csrf();
	 *
	 *     // In the controller
	 *     if ( ! Security::check($this->request->post('csrf'))) ...
	 *
	 * @param   string  $name  input name
	 * @return  string
	 * @uses    Security::token
	 * @uses    Form::hidden
	 */
	public static function csrf(string $name = 'csrf'): string
	{
		return Form::hidden($name, Security::token());
	}

	/**
	 * Creates a password form input.
	 *
	 *     echo Form::password('password');
	 *
	 * @param   string  $name       input name
	 * @param   string  $value      input value
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function password(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'password';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a file upload form input. No input value can be specified.
	 *
	 *     echo Form::file('image');
	 *
	 * @param   string  $name       input name
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function file(string $name, ?array $attributes = null): string
	{
		$attributes['type'] = 'file';

		return Form::input($name, null, $attributes);
	}

	/**
	 * Creates a checkbox form input.
	 *
	 *     echo Form::checkbox('remember_me', 1, (bool) $remember);
	 *
	 * @param   string  $name       input name
	 * @param   string  $value      input value
	 * @param   bool $checked    checked status
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function checkbox(string $name, string|int|float|bool|null $value = null, bool $checked = false, ?array $attributes = null): string
	{
		$attributes['type'] = 'checkbox';

		if ($checked === true)
		{
			// Make the checkbox active
			$attributes[] = 'checked';
		}

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a radio form input.
	 *
	 *     echo Form::radio('like_cats', 1, $cats);
	 *     echo Form::radio('like_cats', 0, ! $cats);
	 *
	 * @param   string  $name       input name
	 * @param   string  $value      input value
	 * @param   bool $checked    checked status
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function radio(string $name, string|int|float|bool|null $value = null, bool $checked = false, ?array $attributes = null): string
	{
		$attributes['type'] = 'radio';

		if ($checked === true)
		{
			// Make the radio active
			$attributes[] = 'checked';
		}

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a textarea form input.
	 *
	 *     echo Form::textarea('about', $about);
	 *
	 * @param   string  $name           textarea name
	 * @param   string  $body           textarea body
	 * @param   array<int|string, mixed>   $attributes     html attributes
	 * @param   bool $double_encode  encode existing HTML characters
	 * @return  string
	 * @uses    HTML::attributes
	 * @uses    HTML::chars
	 */
	public static function textarea(string $name, ?string $body = '', ?array $attributes = null, bool $double_encode = true): string
	{
		// Set the input name
		$attributes['name'] = $name;

		// Add default rows and cols attributes (required)
		$attributes += ['rows' => 10, 'cols' => 50];

		return '<textarea'.HTML::attributes($attributes).'>'.HTML::chars($body, $double_encode).'</textarea>';
	}

	/**
	 * Creates a select form input.
	 *
	 *     echo Form::select('country', $countries, $country);
	 *
	 * [!!] Support for multiple selected options was added in v3.0.7.
	 *
	 * @param   string  $name       input name
	 * @param   array<int|string, mixed>   $options    available options
	 * @param   array<int, string>|float|int|string|null   $selected   selected option string, or an array of selected options
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    HTML::attributes
	 */
	public static function select(string $name, ?array $options = null, string|int|float|array|null $selected = null, ?array $attributes = null): string
	{
		// Set the input name
		$attributes['name'] = $name;

		if (is_array($selected))
		{
			// This is a multi-select, god save us!
			$attributes[] = 'multiple';
		}

		// The selected values as a list of strings (null selects nothing), so
		// they can be compared exactly with the option values
		$selected = array_map('strval', ($selected === null) ? [] : (array) $selected);

		$html = [];

		foreach ( (array) $options as $value => $label)
		{
			if (is_array($label))
			{
				// A nested array is a group of options
				$group = [];

				foreach ($label as $_value => $_label)
				{
					$group[] = Form::_option($_value, $_label, $selected);
				}

				$html[] = '<optgroup'.HTML::attributes(['label' => $value]).'>'."\n".implode("\n", $group)."\n".'</optgroup>';
			}
			else
			{
				$html[] = Form::_option($value, $label, $selected);
			}
		}

		// Compile the options into a single string
		$html = $html ? "\n".implode("\n", $html)."\n" : '';

		return '<select'.HTML::attributes($attributes).'>'.$html.'</select>';
	}

	/**
	 * Creates one option of a select field.
	 *
	 * @param   string|int  $value     option value
	 * @param   mixed       $label     text shown for the option
	 * @param   array<int, string>       $selected  selected values, as strings
	 * @return  string
	 * @uses    HTML::attributes
	 * @uses    HTML::chars
	 */
	protected static function _option(string|int $value, mixed $label, array $selected): string
	{
		// Force value to be string
		$value = (string) $value;

		$option = ['value' => $value];

		if (in_array($value, $selected, true))
		{
			// This option is selected
			$option[] = 'selected';
		}

		return '<option'.HTML::attributes($option).'>'.HTML::chars((is_scalar($label) OR $label instanceof Stringable) ? $label : '', false).'</option>';
	}

	/**
	 * Creates a submit form input.
	 *
	 *     echo Form::submit(null, 'Login');
	 *
	 * @param   string  $name       input name
	 * @param   string  $value      input value
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function submit(string $name, string|int|float|bool|null $value, ?array $attributes = null): string
	{
		$attributes['type'] = 'submit';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a image form input.
	 *
	 *     echo Form::image(null, null, array('src' => 'media/img/login.png'));
	 *
	 * @param   string  $name       input name
	 * @param   string  $value      input value
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @param   bool $index      add index file to URL?
	 * @return  string
	 * @uses    Form::input
	 */
	public static function image(string $name, string|int|float|bool|null $value, ?array $attributes = null, bool $index = false): string
	{
		if ( ! empty($attributes['src']) AND is_string($attributes['src']))
		{
			if (strpos($attributes['src'], '://') === false AND strncmp($attributes['src'], '//', 2))
			{
				// Add the base URL
				$attributes['src'] = URL::base($index).$attributes['src'];
			}
		}

		$attributes['type'] = 'image';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a button form input. Note that the body of a button is NOT escaped,
	 * to allow images and other HTML to be used.
	 *
	 *     echo Form::button('save', 'Save Profile', array('type' => 'submit'));
	 *
	 * @param   string  $name       input name
	 * @param   string  $body       input value
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    HTML::attributes
	 */
	public static function button(string $name, string $body, ?array $attributes = null): string
	{
		// Set the input name
		$attributes['name'] = $name;

		return '<button'.HTML::attributes($attributes).'>'.$body.'</button>';
	}

	/**
	 * Creates a form label. Label text is not automatically translated.
	 *
	 *     echo Form::label('username', 'Username');
	 *
	 * @param   string  $input      target input
	 * @param   string  $text       label text
	 * @param   array<int|string, mixed>   $attributes html attributes
	 * @return  string
	 * @uses    HTML::attributes
	 */
	public static function label(string $input, ?string $text = null, ?array $attributes = null): string
	{
		if ($text === null)
		{
			// Use the input name as the text
			$text = ucwords( (string) preg_replace('/[\W_]+/', ' ', $input));
		}

		// Set the label target
		$attributes['for'] = $input;

		return '<label'.HTML::attributes($attributes).'>'.$text.'</label>';
	}

	/**
	 * Creates a form input for an email address.
	 *
	 *     echo Form::email('email', $user->email);
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function email(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'email';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a web address. Named "url_field" to keep it apart from the [URL] helper.
	 *
	 *     echo Form::url_field('website', $user->website);
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function url_field(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'url';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a telephone number.
	 *
	 *     echo Form::tel('phone', $user->phone);
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function tel(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'tel';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a search text.
	 *
	 *     echo Form::search('q', $query);
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function search(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'search';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a number.
	 *
	 *     echo Form::number('quantity', 1, ['min' => 1, 'max' => 10]);
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function number(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'number';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a number chosen with a slider.
	 *
	 *     echo Form::range('volume', 5, ['min' => 0, 'max' => 10]);
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function range(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'range';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a date (the value is written as YYYY-MM-DD).
	 *
	 *     echo Form::date('birthday', '2000-12-31');
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function date(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'date';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a time (the value is written as HH:MM).
	 *
	 *     echo Form::time('opens', '09:30');
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function time(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'time';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a form input for a colour (the value is written as #rrggbb).
	 *
	 *     echo Form::color('background', '#ffeedd');
	 *
	 * @param   string  $name        input name
	 * @param   string|int|float|bool|null  $value  input value
	 * @param   array<int|string, mixed>|null  $attributes  html attributes
	 * @return  string
	 * @uses    Form::input
	 */
	public static function color(string $name, string|int|float|bool|null $value = null, ?array $attributes = null): string
	{
		$attributes['type'] = 'color';

		return Form::input($name, $value, $attributes);
	}

}
