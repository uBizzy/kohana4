# KO9 compatibility module

Lets an application written for Konine run on this framework without renaming its classes.

## What it provides

| Old name | Is now |
|---|---|
| `KO9` | `Kohana` |
| `KO9_Arr`, `KO9_Request`, ... | `Kohana_Arr`, `Kohana_Request`, ... |
| `KO9_KO9_Exception` | `Kohana_Kohana_Exception` |
| `KO9_START_TIME`, `KO9_START_MEMORY` | `KOHANA_START_TIME`, `KOHANA_START_MEMORY` |
| `KONINE_ENV` server variable | `KOHANA_ENV` |
| `views/ko9/error.php` in the application | used as the error page |

The old names are aliases, not separate classes, so `KO9_Arr` and `Kohana_Arr` are the same class. This covers every class and interface of the framework and of all enabled modules.

## Enabling it

In `application/bootstrap.php`:

    $modules = array(
        'ko9' => MODPATH.'ko9',
        // ...
    );

## What it cannot do

The module is loaded by `Kohana::modules()`, so the old names do not exist before that call. The files that run earlier must use the new names:

- `public/index.php` and `application/bootstrap.php`: start from the ones shipped with this version.
- A class the application puts in `application/classes/KO9.php` to extend the core must be renamed to `application/classes/Kohana.php` (`class Kohana extends Kohana_Core`).

Everything else in the upgrade guide (return types, typed properties) applies to code using the old names as well: an alias changes the name of a class, not its methods.
