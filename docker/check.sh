#!/bin/bash
# Runs every check of the framework, in the order of the fastest first,
# and stops at the first one that fails. The same script runs in CI.
#
#   docker/check.sh                    everything
#   docker/check.sh --filter Auth      the same, with these options for PHPUnit
#
# With Docker, from the project root:
#
#   docker compose -f docker/compose.yml run --rm php docker/check.sh
#
# Without Docker it needs PHP 8.4 with the extensions of docker/Dockerfile,
# and Composer. Tests of a database or cache server that is not configured
# are skipped: see modules/unittest/guide/unittest/index.md.
set -e

cd "$(dirname "$0")/.."

step() {
	echo
	echo "== $1"
}

if [ ! -d vendor ]; then
	step 'Composer packages'
	composer install --no-interaction --no-progress
fi

step 'Syntax'
find system modules application public docker $(test -d extra && echo extra) -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n 50 -P 4 php -l > /dev/null

step 'Code style'
vendor/bin/phpcs -q

step 'Nullable types are written as ?Type'
if grep -rnE '[(,] *[A-Za-z_\\]+\|null +&?\$' --include='*.php' system/classes modules/*/classes; then
	echo 'Write "?Type $name" instead of "Type|null $name".'
	exit 1
fi

step 'Everything has a type'
php docker/check-types.php

step 'No assignment that ends before "and" or "or"'
php docker/check-logic.php

step 'Every class loads'
php docker/load-all-classes.php

step 'Static analysis'
vendor/bin/phpstan analyse --no-progress --memory-limit=1G

step 'Tests'
vendor/bin/phpunit "$@"

# The modules of extra/ are not part of the core and have a repository of
# their own: when they are there, they are checked apart, with the same rules
if [ -d extra ]; then
	step 'Extra modules: code style, types, static analysis and tests'
	vendor/bin/phpcs -q extra
	php docker/check-types.php extra/*/classes
	vendor/bin/phpstan analyse -c extra/phpstan.neon --no-progress --memory-limit=1G
	vendor/bin/phpunit -c extra/phpunit.xml
fi

echo
echo 'All checks passed.'
