#!/usr/bin/env bash

php vendor/phpstan/phpstan/phpstan analyse --memory-limit=1G -l 8 -c phpstan.neon src
