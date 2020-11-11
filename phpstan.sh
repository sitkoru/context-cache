#!/usr/bin/env bash

php vendor/phpstan/phpstan/phpstan analyse -l 8 -c phpstan.neon src
