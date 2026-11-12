# XSS

Cross-site scripting (XSS) is when text written by one visitor is run as code in the browser of another. The cure is one habit: **escape every value when you print it**.

```php
<!-- Wrong: a name like <script>...</script> runs -->
<p><?php echo $user->name ?></p>

<!-- Right -->
<p><?php echo HTML::chars($user->name) ?></p>
```

`HTML::chars()` turns `<`, `>`, `&` and quotes into harmless text. Use it for everything that is not HTML you wrote yourself: values from forms, the address, cookies, the database, other sites.

## Where a value is printed matters

```php
<!-- Between tags, and in attribute values (always with quotes) -->
<p><?php echo HTML::chars($comment) ?></p>
<input value="<?php echo HTML::chars($value) ?>">

<!-- An address: check it too -->
<?php if (Valid::url($user->website)): ?>
	<a href="<?php echo HTML::chars($user->website) ?>">Website</a>
<?php endif ?>

<!-- Inside a script: as JSON, never glued into the code -->
<script nonce="<?php echo HTML::chars(Security::nonce()) ?>">
	var user = <?php echo json_encode($user->name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
```

[!!] `HTML::chars()` does not make an address safe. `javascript:alert(1)` has no special characters. Check addresses with `Valid::url()`, which only accepts addresses with a real scheme, and print only `http` and `https` ones.

## The helpers escape for you

Values given to these are escaped; do not escape them twice.

```php
echo Form::input('title', $title);
echo Form::textarea('body', $body);
echo HTML::image($src, ['alt' => $alt]);
echo HTML::attributes(['title' => $title]);
```

The **text of a link** is HTML, so escape it yourself:

```php
echo HTML::anchor('user/'.$user->id, HTML::chars($user->name));
```

## When visitors may write HTML

Do not try to clean HTML with patterns of your own. Either:

- store plain text and format it when shown (`Text::auto_p()`, `Text::auto_link()`, or a Markdown parser), or
- run the HTML through a library made for this, such as HTML Purifier, before saving it.

```php
// Plain text with paragraphs and links: escape first, then format
echo Text::auto_p(Text::auto_link(HTML::chars($comment)));
```

## A second line of defence

A Content-Security-Policy tells the browser to run only scripts you allow, so a script that slips through does nothing. See [Security Headers](headers).

```php
// application/bootstrap.php
HTTP::$security_headers['content-security-policy'] = "default-src 'self'; script-src 'self' 'nonce-{nonce}'";
```
