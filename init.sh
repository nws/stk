#!/bin/sh

cp stk/Makefile.example Makefile

cp stk/run.php.example run.php
chmod 755 run.php

cp stk/index.php.example index.php

mkdir cfg

mkdir -p smarty/compile
chmod 777 smarty/compile

mkdir tiles
mkdir tiles/www
mkdir tiles/scripts
mkdir -p tmpl/www

