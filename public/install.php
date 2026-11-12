<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kohana Installation</title>
    <?php if (is_file(SYSPATH.'views/kohana/favicon.svg')): ?>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,<?php echo base64_encode((string) file_get_contents(SYSPATH.'views/kohana/favicon.svg')) ?>">
    <?php endif ?>
    <meta name="title" content="Kohana Installation">
    <meta name="description" content="The following tests have been run to determine if Kohana will work in your environment.">
    <meta name="author" content="Konine Team">
    <?php
    // The colours of the framework's own pages, light and dark, when
    // the system directory is where it should be (this page runs
    // before the framework has started, and says so when it is not)
    $theme_class  = SYSPATH.'classes/Kohana/Theme'.EXT;
    $theme_config = SYSPATH.'config/theme'.EXT;
    $theme_script = SYSPATH.'views/kohana/theme.js';
    $themed       = (is_file($theme_class) AND is_file($theme_config) AND is_file($theme_script));

    if ($themed AND ! class_exists('Kohana_Theme', false))
    {
        require $theme_class;
    }
    ?>
    <style>
        <?php if ($themed): ?>
        <?php echo Kohana_Theme::build((array) include $theme_config) ?>

        <?php else: ?>
        :root { --k-scheme: light; --k-bg: #f6f3ef; --k-surface: #fffdfa; --k-text: #33383b; --k-muted: #646b70; --k-border: #e3ddd5; --k-link: #356a8f; --k-error: #efb8b3; --k-error-text: #5a2622; --k-app: #d7ead3; --k-app-text: #274a2b; --k-cause-text: #5c3a17; --k-edge: rgb(0 0 0 / 0.2); --k-radius: 4px; --k-font: system-ui, sans-serif; --k-font-code: monospace; }
        <?php endif ?>

        html { color-scheme: var(--k-scheme); }
        body { margin: 0; background: var(--k-bg); color: var(--k-text); font: 16px/1.4 var(--k-font); }
        .container { position: relative; max-width: 800px; margin: 10px auto; background: var(--k-surface); padding: 20px 15px; border: 1px solid var(--k-border); border-radius: calc(var(--k-radius) * 3); }
        table { margin: 20px 0; }
        h1 { margin: 5px 0; }
        .k-logo { display: block; height: 44px; width: auto; margin: 0 0 15px; }
        p { margin: 10px 0; }
        a, a:visited { color: var(--k-link); }
        th { text-align: left; width: 30%; }
        td, th { padding: 5px 10px 5px 0; }
        .pass { color: var(--k-app-text); }
        .warning { color: var(--k-cause-text); }
        .fail { color: var(--k-error-text); }
        code { font: 15px var(--k-font-code); background: var(--k-border); border-radius: var(--k-radius); padding: 0 0.3em; }
        .alert { padding: 20px; border-radius: calc(var(--k-radius) * 3); border: 1px solid var(--k-edge); margin-bottom: 20px; }
        .alert code { background: none; padding: 0; }
        .danger { color: var(--k-error-text); background: var(--k-error); }
        .success { color: var(--k-app-text); background: var(--k-app); }
        .icon { display: inline-block; margin-right: 10px; font-weight: 700; }
        .k-theme-button { position: absolute; top: 20px; right: 15px; font: inherit; font-size: 0.8em; padding: 0.1em 0.8em; border: 1px solid var(--k-edge); border-radius: 999px; background: none; color: var(--k-muted); cursor: pointer; }
    </style>
    <?php if ($themed): ?>
    <script><?php echo file_get_contents($theme_script) ?></script>
    <?php endif ?>
</head>

<body>
    <div class="container">
        <?php if ($themed): ?>
        <button type="button" class="k-theme-button" data-k-theme-button hidden title="Colours: follow the system, light or dark">Theme</button>
        <?php endif ?>
        <?php if (is_file(SYSPATH.'views/kohana/logo.svg')): ?>
        <?php echo file_get_contents(SYSPATH.'views/kohana/logo.svg') ?>
        <?php endif ?>
        <h1>Environment Tests</h1>
        <p>
            The following tests have been run to determine if Kohana will work in your environment. If any of the tests have failed, see "Installation" in the user guide (<code>system/guide/kohana/install.md</code>) for how to correct the problem.
        </p>

        <?php $failed = false ?>

        <table>
            <tbody>
                <tr>
                    <th>PHP Version</th>
                    <?php if (version_compare(PHP_VERSION, '8.4', '>=')): ?>
                    <td class="pass">
                        <?php echo PHP_VERSION ?>
                    </td>
                    <?php else: $failed = true ?>
                    <td class="fail">Kohana requires PHP 8.4 or newer, this version is
                        <?php echo PHP_VERSION ?>.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>System Directory</th>
                    <?php if (is_dir(SYSPATH) AND is_file(SYSPATH.'classes/Kohana'.EXT)): ?>
                    <td class="pass">
                        <?php echo SYSPATH ?>
                    </td>
                    <?php else: $failed = true ?>
                    <td class="fail">The configured <code>system</code> directory does not exist or does not contain required files.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Application Directory</th>
                    <?php if (is_dir(APPPATH) AND is_file(APPPATH.'bootstrap'.EXT)): ?>
                    <td class="pass">
                        <?php echo APPPATH ?>
                    </td>
                    <?php else: $failed = true ?>
                    <td class="fail">The configured <code>application</code> directory does not exist or does not contain required files.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Cache Directory</th>
                    <?php if (is_dir(APPPATH) AND is_dir(APPPATH.'cache') AND is_writable(APPPATH.'cache')): ?>
                    <td class="pass">
                        <?php echo APPPATH.'cache/' ?>
                    </td>
                    <?php else: $failed = true ?>
                    <td class="fail">The <code><?php echo APPPATH.'cache/' ?></code> directory is not writable.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Logs Directory</th>
                    <?php if (is_dir(APPPATH) AND is_dir(APPPATH.'logs') AND is_writable(APPPATH.'logs')): ?>
                    <td class="pass">
                        <?php echo APPPATH.'logs/' ?>
                    </td>
                    <?php else: $failed = true ?>
                    <td class="fail">The <code><?php echo APPPATH.'logs/' ?></code> directory is not writable.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>PCRE UTF-8</th>
                    <?php if ( ! @preg_match('/^.$/u', 'ñ')): $failed = true ?>
                    <td class="fail"><a href="http://php.net/pcre">PCRE</a> has not been compiled with UTF-8 support.</td>
                    <?php elseif ( ! @preg_match('/^\pL$/u', 'ñ')): $failed = true ?>
                    <td class="fail"><a href="http://php.net/pcre">PCRE</a> has not been compiled with Unicode property support.</td>
                    <?php else: ?>
                    <td class="pass">Pass</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>SPL Enabled</th>
                    <?php if (function_exists('spl_autoload_register')): ?>
                    <td class="pass">Pass</td>
                    <?php else: $failed = true ?>
                    <td class="fail">PHP <a href="http://www.php.net/spl">SPL</a> is either not loaded or not compiled in.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Reflection Enabled</th>
                    <?php if (class_exists('ReflectionClass')): ?>
                    <td class="pass">Pass</td>
                    <?php else: $failed = true ?>
                    <td class="fail">PHP <a href="http://www.php.net/reflection">reflection</a> is either not loaded or not compiled in.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Filters Enabled</th>
                    <?php if (function_exists('filter_list')): ?>
                    <td class="pass">Pass</td>
                    <?php else: $failed = true ?>
                    <td class="fail">The <a href="http://www.php.net/filter">filter</a> extension is either not loaded or not compiled in.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Iconv Extension Loaded</th>
                    <?php if (extension_loaded('iconv')): ?>
                    <td class="pass">Pass</td>
                    <?php else: $failed = true ?>
                    <td class="fail">The <a href="http://php.net/iconv">iconv</a> extension is not loaded.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Intl Extension Loaded</th>
                    <?php if (extension_loaded('intl')): ?>
                    <td class="pass">Pass</td>
                    <?php else: $failed = true ?>
                    <td class="fail">The <a href="http://php.net/intl">intl</a> extension is not loaded.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Mbstring Extension Loaded</th>
                    <?php if (extension_loaded('mbstring')): ?>
                    <td class="pass">Pass</td>
                    <?php else: $failed = true ?>
                    <td class="fail">The <a href="http://php.net/mbstring">mbstring</a> extension is not loaded.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Character Type (CTYPE) Extension</th>
                    <?php if ( ! function_exists('ctype_digit')): $failed = true ?>
                    <td class="fail">The <a href="http://php.net/ctype">ctype</a> extension is not enabled.</td>
                    <?php else: ?>
                    <td class="pass">Pass</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>URI Determination</th>
                    <?php if (isset($_SERVER['REQUEST_URI']) OR isset($_SERVER['PHP_SELF']) OR isset($_SERVER['PATH_INFO'])): ?>
                    <td class="pass">Pass</td>
                    <?php else: $failed = true ?>
                    <td class="fail">Neither <code>$_SERVER['REQUEST_URI']</code>, <code>$_SERVER['PHP_SELF']</code>, or <code>$_SERVER['PATH_INFO']</code> is available.</td>
                    <?php endif ?>
                </tr>
            </tbody>
        </table>

        <?php if ($failed === true): ?>
        <div id="results" class="alert danger">
            <span class="icon" aria-hidden="true">&#10006;</span> Kohana may not work correctly with your environment.
        </div>
        <?php else: ?>
        <div id="results" class="alert success">
            <span class="icon" aria-hidden="true">&#10004;</span> Your environment passed all requirements. Remove or rename the <code>install<?php echo EXT ?></code> file now.
        </div>
        <?php endif ?>

        <h1>Optional Tests</h1>

        <p>
            The following extensions are not required to run the Kohana core, but if enabled can provide access to additional classes.
        </p>

        <table>
            <tbody>
                <tr>
                    <th>cURL Enabled</th>
                    <?php if (extension_loaded('curl')): ?>
                    <td class="pass">Pass</td>
                    <?php else: ?>
                    <td class="fail">Kohana can use the <a href="http://php.net/curl">cURL</a> extension for external requests.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>GD Enabled</th>
                    <?php
                    if (extension_loaded('gd') && version_compare(GD_VERSION, '2.0', '>=')):
                        if (gd_info()['WebP Support'] && gd_info()['BMP Support']):
                    ?>
                            <td class="pass">Pass</td>
                        <?php else: ?>
                            <td class="warning">For full support we recommend installing GD with 'webp' and 'bmp' support.</td>
                        <?php endif; ?>
                    <?php else: ?>
                    <td class="fail">Kohana can use <a href="http://php.net/gd">GD</a> >= v2 for Image manipulation</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>Imagick Enabled</th>
					<?php
					if (
					        extension_loaded('imagick') &&
                            preg_match('/ImageMagick ([0-9]+\.[0-9]+\.[0-9]+)/', Imagick::getVersion()['versionString'], $api_version) === 1 &&
							version_compare($api_version[1], '6.9', '>=')
                    ):
						if (Imagick::queryFormats('WEBP') && Imagick::queryFormats('BMP')):
							?>
                            <td class="pass">Pass</td>
						<?php else: ?>
                            <td class="warning">For full support we recommend installing imagick with 'webp' and 'bmp' support.</td>
						<?php endif; ?>
					<?php else: ?>
                        <td class="fail">Kohana can use <a href="http://php.net/imagick">Imagick</a> >= v6.9 for Image manipulation.</td>
					<?php endif ?>
                </tr>
                <tr>
                    <th>MySQLi Enabled</th>
                    <?php if (function_exists('mysqli_connect')): ?>
                    <td class="pass">Pass</td>
                    <?php else: ?>
                    <td class="fail">Kohana can use the <a href="http://php.net/mysqli">MySQLi</a> extension to support MySQL databases.</td>
                    <?php endif ?>
                </tr>
                <tr>
                    <th>PDO Enabled</th>
                    <?php if (class_exists('PDO')): ?>
                    <td class="pass">Pass</td>
                    <?php else: ?>
                    <td class="fail">Kohana can use <a href="http://php.net/pdo">PDO</a> to support additional databases.</td>
                    <?php endif ?>
                </tr>
            </tbody>
        </table>

    </div>
</body>

</html>
