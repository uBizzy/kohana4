<?php

/**
 * Interface that all minion tasks must implement
 *
 * @package    Kohana/Minion
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Minion_Task {

	/**
	 * The separator used to separate different levels of tasks
	 * @var string
	 */
	public static string $task_separator = ':';

	/**
	 * Converts a task (e.g. db:migrate to a class name)
	 *
	 * @param string  $task  Task name
	 * @return string Class name
	 */
	public static function convert_task_to_class_name(string $task): string
	{
		$task = trim($task);

		if (empty($task))
			return '';

		return 'Task_'.implode('_', array_map('ucfirst', explode(Minion_Task::$task_separator ?: ':', $task)));
	}

	/**
	 * Gets the task name of a task class / task object
	 *
	 * @param  string|object  $class  The task class / object
	 * @return string             The task name
	 */
	public static function convert_class_to_task(string|object $class): string
	{
		if (is_object($class))
		{
			$class = get_class($class);
		}

		return strtolower(str_replace('_', Minion_Task::$task_separator, substr($class, 5)));
	}

	/**
	 * Factory for loading minion tasks
	 *
	 * @param  array<int|string, mixed>  $options  An array of command line options. It should contain the 'task' key
	 * @throws Minion_Exception_InvalidTask
	 * @return Minion_Task The Minion task
	 */
	public static function factory(array $options): Minion_Task
	{
		if (($task = Arr::get($options, 'task')) !== null)
		{
			unset($options['task']);
		}
		else if (($task = Arr::get($options, 0)) !== null)
		{
			// The first positional argument (aka 0) may be the task name
			unset($options[0]);
		}
		else
		{
			// If we didn't get a valid task, generate the help
			$task = 'help';
		}

		// Options every task has: they change how Minion_CLI behaves and
		// are not passed on to the task
		if (array_key_exists('no-interaction', $options))
		{
			Minion_CLI::$interactive = false;
		}

		if (array_key_exists('no-color', $options))
		{
			Minion_CLI::$colors = false;
		}

		unset($options['no-interaction'], $options['no-color']);

		$class = Minion_Task::convert_task_to_class_name(is_string($task) ? $task : '');

		if ( ! class_exists($class))
		{
			throw new Minion_Exception_InvalidTask(
				'There is no task ":task". Run ":command" to see the tasks.',
				[':task' => $task, ':command' => Minion_Task::command()]
			);
		}

		$class = new $class;

		if ( ! $class instanceof Minion_Task)
		{
			throw new Minion_Exception_InvalidTask(
				'There is no task ":task". Run ":command" to see the tasks.',
				[':task' => $task, ':command' => Minion_Task::command()]
			);
		}

		$class->set_options($options);

		// Show the help page for this task if requested
		if (array_key_exists('help', $options))
		{
			$class->_method = '_help';
		}

		return $class;
	}

	/**
	 * The list of options this task accepts and their default values.
	 *
	 *     protected $_options = array(
	 *         'limit' => 4,
	 *         'table' => null,
	 *     );
	 *
	 * @var array<int|string, mixed>
	 */
	protected array $_options = [];

	/**
	 * Populated with the accepted options for this task.
	 * This array is automatically populated based on $_options.
	 *
	 * @var array<int, int|string>  names of the options the task accepts
	 */
	protected array $_accepted_options = [];

	protected string $_method = '_execute';

	protected function __construct()
	{
		// Populate $_accepted_options based on keys from $_options
		$this->_accepted_options = array_keys($this->_options);
	}

	/**
	 * The file that get's passes to Validation::errors() when validation fails
	 * @var string
	 */
	protected string $_errors_file = 'validation';

	/**
	 * Gets the task name for the task
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		// The name is worked out from the class of this task
		return Minion_Task::convert_class_to_task($this);
	}

	/**
	 * Sets options for this task
	 *
	 * $param  array  the array of options to set
	 * @param   array<int|string, mixed>  $options
	 * @return static
	 */
	public function set_options(array $options): static
	{
		foreach ($options as $key => $value)
		{
			$this->_options[$key] = $value;
		}

		return $this;
	}

	/**
	 * Get the options that were passed into this task with their defaults
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_options(): array
	{
		return (array) $this->_options;
	}

	/**
	 * Get a set of options that this task can accept
	 *
	 * @return array<int, int|string>
	 */
	public function get_accepted_options(): array
	{
		return (array) $this->_accepted_options;
	}

	/**
	 * Adds any validation rules/labels for validating _options
	 *
	 *     public function build_validation(Validation $validation): Validation
	 *     {
	 *         return parent::build_validation($validation)
	 *             ->rule('paramname', 'not_empty'); // Require this param
	 *     }
	 *
	 * @param  Validation  $validation  the validation object to add rules to
	 *
	 * @return Validation
	 */
	public function build_validation(Validation $validation): Validation
	{
		// Add a rule to each key making sure it's in the task
		foreach ($validation->data() as $key => $value)
		{
			$validation->rule( (string) $key, [$this, 'valid_option'], [':validation', ':field']);
		}

		return $validation;
	}

	/**
	 * Returns $_errors_file
	 *
	 * @return string
	 */
	public function get_errors_file(): string
	{
		return $this->_errors_file;
	}

	/**
	 * Execute the task with the specified set of options
	 *
	 * @return void
	 */
	public function execute(): void
	{
		$options = $this->get_options();

		// Validate $options
		$validation = Validation::factory($options);
		$validation = $this->build_validation($validation);

		if ( $this->_method != '_help' AND ! $validation->check())
		{
			foreach ($validation->errors($this->get_errors_file()) as $option => $error)
			{
				Minion_CLI::error('--'.$option.': '.(is_string($error) ? $error : ''));
			}

			// A task that did not run must not look like a success
			throw new Minion_Exception_InvalidOptions('Run ":command :task --help" to see the options of this task.', [
				':command' => Minion_Task::command(),
				':task'    => Minion_Task::convert_class_to_task($this),
			]);
		}
		else
		{
			// Finally, run the task
			$method = $this->_method;
			$output = $this->{$method}($options);

			echo is_scalar($output) ? $output : '';
		}
	}

	/**
	 * @param   array<int|string, mixed>  $params
	 */
	abstract protected function _execute(array $params): mixed;

	/**
	 * Outputs help for this task
	 *
	 * @param   array<int|string, mixed>  $params
	 * @return null
	 */
	protected function _help(array $params): mixed
	{
		$tasks = $this->_compile_task_list(Kohana::list_files('classes/task'));

		$inspector = new ReflectionClass($this);

		list($description, $tags) = $this->_parse_doccomment(static::_doc_comment($inspector));

		$view = View::factory('minion/help/task')
			->set('description', $description)
			->set('tags', (array) $tags)
			->set('options', array_intersect_key($this->_options, array_flip($this->_accepted_options)))
			->set('task', Minion_Task::convert_class_to_task($this));

		echo $view;

		return null;
	}


	public function valid_option(Validation $validation, string $option): void
	{
		if ( ! in_array($option, $this->_accepted_options))
		{
			$validation->error($option, 'minion_option');
		}
	}

	/**
	 * Parses a doccomment, extracting both the comment and any tags associated
	 *
	 * Based on the code in Kodoc::parse()
	 *
	 * @param string|false  $comment  The comment to parse
	 * @return array{string, array<string, string>} First element is the comment, second is an array of tags
	 */
	protected function _parse_doccomment(string|false $comment): array
	{
		// Normalize all new lines to \n
		$comment = str_replace(["\r\n", "\n"], "\n", (string) $comment);

		// Remove the phpdoc open/close tags and split
		$comment = array_slice(explode("\n", $comment), 1, -1);

		// Tag content
		$tags = [];

		foreach ($comment as $i => $line)
		{
			// Remove all leading whitespace
			$line = (string) preg_replace('/^\s*\* ?/m', '', $line);

			// Search this line for a tag
			if (preg_match('/^@(\S+)(?:\s*(.+))?$/', $line, $matches))
			{
				// This is a tag line
				unset($comment[$i]);

				$name = $matches[1];
				$text = isset($matches[2]) ? $matches[2] : '';

				$tags[$name] = $text;
			}
			else
			{
				$comment[$i] = (string) $line;
			}
		}

		$comment = trim(implode("\n", $comment));

		return [$comment, $tags];
	}

	/**
	 * Compiles a list of available tasks from a directory structure
	 *
	 * @param  array<int|string, mixed>  $files  Directory structure of tasks
	 * @param  string  $prefix  prefix
	 * @return array<int, string> Compiled tasks
	 */
	protected function _compile_task_list(array $files, string $prefix = ''): array
	{
		$output = [];

		foreach ($files as $file => $path)
		{
			$file = (string) $file;
			$file = substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1);

			if (is_array($path) AND count($path))
			{
				$task = $this->_compile_task_list($path, $prefix.$file.Minion_Task::$task_separator);

				if ($task)
				{
					$output = array_merge($output, $task);
				}
			}
			else
			{
				$output[] = strtolower($prefix.substr($file, 0, -strlen(EXT)));
			}
		}

		return $output;
	}

	/**
	 * Returns the doc comment of a task class. A class without one (the
	 * empty class that only extends, in the application or in a module)
	 * has the comment of the class it extends.
	 *
	 * @param   ReflectionClass<covariant object>  $class  the class of a task
	 * @return  string  an empty string when no class of the line has one
	 */
	protected static function _doc_comment(ReflectionClass $class): string
	{
		do
		{
			$comment = $class->getDocComment();
			$class   = $class->getParentClass();
		}
		while ($comment === false AND $class !== false);

		return (string) $comment;
	}

	/**
	 * Returns the first paragraph of the doc comment of a task class: the
	 * short description shown in the list of tasks.
	 *
	 * @param   string  $task  task name, e.g. "db:migrate"
	 * @return  string
	 */
	public static function summary(string $task): string
	{
		$class = Minion_Task::convert_task_to_class_name($task);

		if ( ! class_exists($class))
			return '';

		$comment = static::_doc_comment(new ReflectionClass($class));

		// Lines without the comment marks, up to the first empty line or tag
		$summary = [];

		foreach (array_slice(explode("\n", str_replace("\r", '', $comment)), 1, -1) as $line)
		{
			$line = trim( (string) preg_replace('/^\s*\* ?/', '', $line));

			if ($line === '' OR $line[0] === '@')
			{
				if ($summary)
					break;

				continue;
			}

			$summary[] = $line;
		}

		return implode(' ', $summary);
	}

	/**
	 * Returns the command minion was started with, for help texts.
	 *
	 * @return  string
	 */
	public static function command(): string
	{
		$argv = (array) ($_SERVER['argv'] ?? []);

		return is_string($argv[0] ?? null) ? $argv[0] : 'minion';
	}

	public static function set_domain_name(?string $domain_name = ''): void
	{
		if (Request::$initial === null)
		{
			if (empty($domain_name))
			{
				$domain_name = Kohana::$config->load('site.minion_domain_name');
			}

			$domain_name = is_string($domain_name) ? $domain_name : '';

			// Add trailing slash
			Kohana::$base_url = (string) preg_replace('~^https?://[^/]+$~', '$0/', $domain_name);

			// Initialize Request Class
			$request = Request::factory();

			// Set HTTPS for https based urls
			$request->secure(preg_match_all('#(https)://#i', Kohana::$base_url, $result) === 1);

			Request::$initial = $request;
		}
	}
}
