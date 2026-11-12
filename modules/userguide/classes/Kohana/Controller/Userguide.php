<?php
/**
 * Kohana user guide and API browser.
 *
 * @package    Kohana/Userguide
 * @category   Controller
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
abstract class Kohana_Controller_Userguide extends Controller_Template {

	/**
	 * @var  string|View  page template: its file name here, the view itself once before() has run
	 */
	public string|View $template = 'userguide/template';

	/**
	 * @var  Route  route of the media files, set by before()
	 */
	protected Route $media;

	/**
	 * @var  Route  route of the guide pages, set by before()
	 */
	protected Route $guide;

	/**
	 * Prepares the template and the addresses that links start with.
	 *
	 * @return  void
	 */
	public function before(): void
	{
		// Media files and the search list are not pages
		$this->auto_render = ! in_array($this->request->action(), ['media', 'search'], true);

		parent::before();

		$this->media = Route::get('docs/media');
		$this->guide = Route::get('docs/guide');

		$this->_markdown_urls('');

		if ($this->auto_render)
		{
			$this->template->set([
				'title'      => 'User Guide',
				'breadcrumb' => [],
				'menu'       => '',
				'content'    => '',
				'toc'        => [],
				'copyright'  => null,
			]);
		}
	}

	/**
	 * Shows the list of guides.
	 *
	 * @return  void
	 */
	public function index(): void
	{
		$modules = $this->_modules();

		$this->template->title = 'User Guide';
		$this->template->breadcrumb = ['User Guide'];
		$this->template->content = View::factory('userguide/index', ['modules' => $modules]);
		$this->template->menu = View::factory('userguide/menu', ['modules' => $modules]);
	}

	/**
	 * Shows a "not found" page.
	 *
	 * @param   string  $message  what was not found
	 * @return  void
	 */
	public function error(string $message): void
	{
		$this->response->status(404);

		$this->template->title = 'Not found';
		$this->template->content = View::factory('userguide/error', ['message' => $message]);

		$module = $this->_param('module');

		if ($module !== '' AND $this->_enabled($module) AND $this->file($module.'/menu'))
		{
			// Inside a guide: show its menu
			$this->_markdown_urls($module.'/');

			$this->template->menu = Kodoc_Markdown::markdown($this->_get_all_menu_markdown());
			$this->template->breadcrumb = [
				$this->guide->uri() => 'User Guide',
				$this->guide->uri(['module' => $module]) => Kohana::$config->load('userguide.modules.'.$module.'.name'),
				'Not found',
			];
		}
		elseif ($this->request->route() === Route::get('docs/api'))
		{
			$this->template->menu = Kodoc::menu();
			$this->template->breadcrumb = [
				$this->guide->uri() => 'User Guide',
				Route::get('docs/api')->uri() => 'API Browser',
				'Not found',
			];
		}
		else
		{
			$this->template->menu = View::factory('userguide/menu', ['modules' => $this->_modules()]);
			$this->template->breadcrumb = [$this->guide->uri() => 'User Guide', 'Not found'];
		}
	}

	/**
	 * Shows a page of a guide: `guide/<module>/<page>`.
	 *
	 * @return  void
	 */
	public function action_docs(): void
	{
		$module = $this->_param('module');
		$page = rtrim($this->_param('page'), '/');

		if ($module === '')
		{
			// No guide named: list them
			$this->index();
			return;
		}

		if ( ! $this->_enabled($module))
		{
			$this->error('That module does not exist, or its guide is switched off.');
			return;
		}

		// "guide/module" is the index page; "guide/module/index" would be the same page twice
		$file = ($page === 'index') ? false : $this->file($module.'/'.($page ?: 'index'));

		if ( ! $file)
		{
			$this->error('That page of the guide was not found.');
			return;
		}

		$name = Kohana::$config->load('userguide.modules.'.$module.'.name');

		$this->template->title = ($page === '') ? $name : $this->title($page);

		// Links in a page are relative to the file of the page, as on GitHub
		$this->_markdown_urls($module.'/', $page);

		// The headings of the page are read right after it is parsed
		$this->template->content = Kodoc_Markdown::markdown((string) file_get_contents($file));
		$this->template->toc = Kodoc_Markdown::toc();

		// The menu is in the first directory of the guide
		$this->_markdown_urls($module.'/');

		$this->template->menu = Kodoc_Markdown::markdown($this->_get_all_menu_markdown());
		$this->template->copyright = Kohana::$config->load('userguide.modules.'.$module.'.copyright');

		$breadcrumb = [
			$this->guide->uri() => 'User Guide',
			$this->guide->uri(['module' => $module]) => $name,
		];

		if ($page !== '')
		{
			$section = $this->section($page);

			// A section named like the guide itself would only repeat it
			if ($section !== $page AND $section !== $name)
			{
				$breadcrumb[] = $section;
			}

			$breadcrumb[] = $this->template->title;
		}

		$this->template->breadcrumb = $breadcrumb;
	}

	/**
	 * Shows the API browser: the list of classes, or one class.
	 *
	 * @return  void
	 */
	public function action_api(): void
	{
		$class = $this->_param('class');

		$breadcrumb = [
			$this->guide->uri() => 'User Guide',
			Route::get('docs/api')->uri() => 'API Browser',
		];

		if ($class === '')
		{
			$this->template->title = 'API Browser';
			$this->template->content = View::factory('userguide/api/toc')
				->set('classes', Kodoc::class_methods())
				->set('route', $this->request->route());

			$breadcrumb = [$this->guide->uri() => 'User Guide', 'API Browser'];
		}
		else
		{
			try
			{
				$doc = Kodoc_Class::factory($class);
			}
			catch (Throwable $e)
			{
				// No such class, or it cannot be loaded (a missing parent or extension)
				$this->error('That class was not found. Check the address, and that the module of the class is enabled.');
				return;
			}

			if ($doc->class->name !== $class)
			{
				// Another spelling of the name (orm, Orm): go to the real one
				$this->redirect(Route::get('docs/api')->uri(['class' => $doc->class->name]));
			}

			if ( ! Kodoc::show_class($doc))
			{
				$this->error('That class is in a package that is hidden. See the api_packages setting.');
				return;
			}

			$this->template->title = $class;
			$this->template->content = View::factory('userguide/api/class')
				->set('doc', $doc)
				->set('route', $this->request->route());

			$breadcrumb[] = $class;
		}

		$this->template->menu = Kodoc::menu();
		$this->template->breadcrumb = $breadcrumb;
	}

	/**
	 * Sends what the search box searches in, as JSON: every page of every
	 * guide with its text, and every class with the names of its methods.
	 *
	 * @return  void
	 */
	public function action_search(): void
	{
		$entries = [];

		foreach ($this->_modules() as $module => $options)
		{
			foreach ($this->_pages($module) as $page => $file)
			{
				$this->_markdown_urls($module.'/', $page);

				// The text of the page, without its Markdown marks
				$html = Kodoc_Markdown::markdown((string) file_get_contents($file));
				$toc = Kodoc_Markdown::toc();

				$entries[] = [
					'url'      => URL::site($this->guide->uri(['module' => $module, 'page' => ($page === 'index') ? null : $page])),
					'title'    => $toc[0]['name'] ?? $page,
					'section'  => $options['name'],
					'headings' => implode(' | ', array_filter(array_column(array_slice($toc, 1), 'name'), 'is_string')),
					'text'     => trim( (string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'))),
				];
			}
		}

		if (Kohana::$config->load('userguide.api_browser') === true)
		{
			$api = Route::get('docs/api');

			foreach (Kodoc::class_methods() as $class => $methods)
			{
				$entries[] = [
					'url'      => URL::site($api->uri(['class' => $class])),
					'title'    => $class,
					'section'  => 'API',
					'headings' => implode(' | ', $methods),
					'text'     => '',
				];
			}
		}

		$this->response->json($entries);
	}

	/**
	 * Sends an image, style sheet or script of the guide: a file in a
	 * "media/guide" directory of the application or of a module.
	 *
	 * @return  void
	 */
	public function action_media(): void
	{
		$file = $this->_param('file');
		$ext = pathinfo($file, PATHINFO_EXTENSION);

		// Only these kinds of file are sent
		$types = ['css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];

		$path = isset($types[$ext]) ? Kohana::find_file('media/guide', substr($file, 0, -(strlen($ext) + 1)), $ext) : false;

		if ( ! is_string($path))
		{
			$this->response->status(404);
			return;
		}

		// Answer "not changed" when the browser already has this version
		$this->check_cache(hash('sha256', $this->request->uri()).filemtime($path));

		$this->response->body((string) file_get_contents($path));
		$this->response->headers('content-type', $types[$ext]);
		$this->response->headers('last-modified', gmdate('D, d M Y H:i:s', (int) filemtime($path)).' GMT');
	}

	/**
	 * Gives the template the addresses of the style sheet and the script.
	 *
	 * @return  void
	 */
	public function after(): void
	{
		if ($this->auto_render)
		{
			$this->template->styles = [$this->media->uri(['file' => 'guide.css'])];
			$this->template->scripts = [$this->media->uri(['file' => 'guide.js'])];
		}

		parent::after();
	}

	/**
	 * Finds the Markdown file of a page. These all find
	 * `guide/userguide/adding.md`:
	 *
	 *  * userguide/adding
	 *  * userguide/adding.md
	 *  * userguide/adding.markdown
	 *
	 * @param   string  $page  module and page: `module/page`
	 * @return  string|false   path of the file, or false
	 */
	public function file(string $page): string|false
	{
		$page = (string) preg_replace('/\.(md|markdown)$/', '', $page);

		$file = Kohana::find_file('guide', $page, 'md');

		return is_string($file) ? $file : false;
	}

	/**
	 * Returns the title of the menu section a page is in.
	 *
	 * @param   string  $page  name of the page
	 * @return  string  the section, or the name of the page when it is in none
	 */
	public function section(string $page): string
	{
		$section = null;

		foreach (explode("\n", $this->_get_all_menu_markdown()) as $line)
		{
			if (preg_match('/^\s*[-*+]\s+\[[^\]]+\]\(([^)\s]+)\)/', $line, $matches))
			{
				// A link: is it this page?
				if (preg_replace('/\.md$/', '', $matches[1]) === $page)
					return $section ?? $page;
			}
			elseif (preg_match('/^\s*[-*+#]+\s+(.+?)\s*$/', $line, $matches))
			{
				// A line without a link is the title of a section
				$section = trim($matches[1], '* ');
			}
		}

		return $page;
	}

	/**
	 * Returns the title of a page: the text of its link in the menu.
	 *
	 * @param   string  $page  name of the page
	 * @return  string  the title, or the name of the page when it is not in the menu
	 */
	public function title(string $page): string
	{
		if (preg_match('~\[([^\]]+)\]\('.preg_quote($page, '~').'(?:\.md)?\)~mu', $this->_get_all_menu_markdown(), $matches))
			return $matches[1];

		return $page;
	}

	/**
	 * A parameter of the route, as text.
	 *
	 * @param   string  $key  name of the parameter
	 * @return  string  empty when it is missing
	 */
	protected function _param(string $key): string
	{
		$value = $this->request->param($key);

		return is_string($value) ? $value : '';
	}

	/**
	 * Returns the Markdown text of the menu of the current guide.
	 *
	 * @return  string
	 */
	protected function _get_all_menu_markdown(): string
	{
		$file = $this->file($this->_param('module').'/menu');

		return $file ? (string) file_get_contents($file) : '';
	}

	/**
	 * Sets the addresses that the Markdown parser puts before links and images.
	 *
	 * Links are relative to the file they are in: a link in the page
	 * `mvc/views` starts from `guide/<module>/mvc/`. Images are always
	 * relative to the media directory of the guide.
	 *
	 * @param   string  $module  '' or the name of the guide followed by "/"
	 * @param   string  $page    name of the page the links are in, or ''
	 * @return  void
	 */
	protected function _markdown_urls(string $module, string $page = ''): void
	{
		$directory = str_contains($page, '/') ? dirname($page).'/' : '';

		Kodoc_Markdown::$base_url  = URL::site($this->guide->uri()).'/'.$module.$directory;
		Kodoc_Markdown::$image_url = URL::site($this->media->uri()).'/'.$module;
	}

	/**
	 * Tells whether a guide exists and is switched on.
	 *
	 * @param   string  $module  name of the guide
	 * @return  bool
	 */
	protected function _enabled(string $module): bool
	{
		return (bool) Kohana::$config->load('userguide.modules.'.$module.'.enabled');
	}

	/**
	 * Returns the pages of a guide (not its menu).
	 *
	 * @param   string  $module  name of the guide
	 * @return  array<string, string>   page name (as in its address) => path of the file
	 */
	protected function _pages(string $module): array
	{
		/** @var array<string, string> $pages */
		$pages = [];

		$collect = function (array $files) use (&$collect, &$pages, $module): void
		{
			foreach ($files as $name => $path)
			{
				if (is_array($path))
				{
					$collect($path);
				}
				elseif (str_ends_with($name, '.md'))
				{
					// guide/module/sub/page.md is the page "sub/page"
					$page = str_replace(DIRECTORY_SEPARATOR, '/', substr($name, strlen('guide/'.$module.'/'), -3));

					if ($page !== 'menu')
					{
						$pages[$page] = $path;
					}
				}
			}
		};

		$collect(Kohana::list_files('guide/'.$module));

		/** @var array<string, string> $pages  filled by the function above */
		ksort($pages);

		return $pages;
	}

	/**
	 * Returns the guides that are switched on: Kohana first, then the
	 * modules in the order they were enabled.
	 *
	 * @return  array<string, array<string, mixed>>  name of the guide => its settings
	 */
	protected function _modules(): array
	{
		/** @var array<string, array<string, mixed>> $modules  the guides of config/userguide.php */
		$modules = array_reverse( (array) Kohana::$config->load('userguide.modules'));

		if (isset($modules['kohana']))
		{
			$modules = ['kohana' => $modules['kohana']] + $modules;
		}

		return array_filter($modules, fn ($options) => ! empty($options['enabled']));
	}

}
