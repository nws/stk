#!/bin/bash
# TODO:
# check for timezones in mysql

yesno() {
	local prompt default q y n
	prompt="$1"
	default="$2"

	if test "$default" == 'y'; then
		y='Y'
	else
		y='y'
	fi
	if test "$default" == 'n'; then
		n='N'
	else
		n='n'
	fi
	q="$y/$n"

	echo -n "$1 [$q] "
	read a

	a="$(echo "$a"|tr A-Z a-z)"

	if test -z "$a"; then
		a="$default"
	fi

	if test "$a" == 'y'; then
		return 0
	else
		return 1
	fi
}

if [ \( -n "$NONINTERACTIVE" -o -t 1 \) -a -z "$INNER" ]; then
	TF="`mktemp`"
	if test -n "$NONINTERACTIVE"; then
		SKIP_DBCHECK="$SKIP_DBCHECK" NONINTERACTIVE=1 INNER=1 $0 > $TF
	else
		SKIP_DBCHECK="$SKIP_DBCHECK" INNER=1 $0 | tee "$TF"
	fi

	tail -1 "$TF"|grep -q '^ALL DONE$'
	ALL_DONE=$?

	if test "$ALL_DONE" -gt 0; then
		echo -e "\n\nERROR: check.sh did not finish"
		exit 200
	fi

	WC=$(grep WARN "$TF" | wc -l)
	EC=$(grep ERR "$TF" | wc -l)

	if test -n "$NONINTERACTIVE"; then
		grep WARN "$TF"
		grep ERR "$TF"
	else
		if test "$WC" -gt 0; then
			echo -e "\n\nWARNINGS: $WC"
		fi
		if test "$EC" -gt 0; then
			echo -e "\n\nERRORS  : $EC"
		fi
	fi

	rm "$TF"
	exit `expr $WC + $EC`
fi

PROJ_DIR=$(dirname $(readlink -f $0))

echo -n "checking php version ... "

RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (version_compare(PHP_VERSION, \"5.2.1\") === -1) { exit(1); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "your php is too old, we need at least 5.2.1"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done

echo -n "checking php-curl ... "
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (!function_exists(\"curl_init\")) { exit(1); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "please install curl extenstion for php"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done


echo -n "checking php-mysqli ... "
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (!class_exists(\"mysqli\")) { exit(1); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "please install mysqli extenstion for php"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done


echo -n "checking php-gd ... "
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (!function_exists(\"gd_info\")) { exit(1); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "please install gd extenstion for php"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done

echo -n "checking php-xml ... "
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (!class_exists(\"DOMDocument\")) { exit(1); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "please install xml extenstion for php"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done

echo -n "checking php-pecl-pcntl ... "
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (!function_exists(\"pcntl_fork\")) { exit(1); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "please install pcntl extenstion for php"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done

echo -n "checking php-mbstring ... "
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (!function_exists(\"mb_internal_encoding\")) { exit(1); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "please install mbstring extenstion for php"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done

echo -n "checking for magic_quotes_gpc..."
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -r 'if (!function_exists(\"get_magic_quotes_gpc\")) { exit(0); } else { exit( get_magic_quotes_gpc() ); }'||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 1; then
		echo "please turn off magic_quotes_gpc"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	else
		echo ok
	fi
done


if test -z "$NONINTERACTIVE"; then
	if yesno "Should i run a syntax/lint check?" y; then
		echo -n "checking syntax on all *.php files ... "
		bad_files="$(find . -name '*.php' -exec php -l {} \; 2>&1 | grep -v '^No syntax errors detected in')"
		if test -n "$bad_files"; then
			echo -e "ERROR syntax error:\n$bad_files"
		else 
			echo "ok"
		fi

		echo -n "checking for false php open tags in source tree..."
		found=`find . -name "*.php" -exec grep "<?$" '{}' \; -print|grep -v "<?"`
		if test -n "$found"; then
			echo "WARNING found false open tags in files:"
			echo "$found"
			echo ""
		else
			echo "ok"
		fi

		echo -n "checking for false php close tags in source tree..."
		found=`find . -name "*.php" -exec ./bin/wspace.php '{}' \;`
		if test -n "$found"; then
			echo "WARNING found false close tags in files:"
			echo "$found"
			echo ""
		else
			echo "ok"
		fi

		if -x ./phplint.php; then
			echo -n "checking lintedness of all *.php files ... "
			bad_files="$(find . -not -path './smarty/compile/*' -a -not -path './smarty/libs/*' -a -not -path './inc/pear/*' -a -not -path './inc/amazon/*' -a -name '*.php' -exec ./phplint.php {} +)"
			if test -n "$bad_files"; then
				echo -e "ERROR lint failed:\n$bad_files"
			else
				echo "ok"
			fi
		else
			echo "NOTICE: install phplint.php (https://github.com/nws/phplint) to get some extra checks"
		fi
	fi
fi

if test -r cfg/localcfg.php; then
	echo -n "checking syntax of cfg/localcfg.php ..."
	RET=200
	while test "$RET" -gt 1; do
		sh -c "(php -l cfg/localcfg.php >/dev/null||exit)" 2>/dev/null
		RET=$?
		if test "$RET" -eq 255; then
			echo "ERROR parse error in cfg/localcfg.php"
			exit 1
		elif test "$RET" -gt 0; then
			#segfault crap
			echo -n "."
		else
			CNT=`grep 'config::\\\$' cfg/localcfg.php|wc -l`
			if test $CNT -gt 0; then
				echo ok
			else
				echo "WARNING: cfg/localcfg.php exists, but nothing overriden fron config::"
			fi
		fi
	done
else
	echo "cfg/localcfg.php does not exists"
fi

echo -n "checking if config makes sense..."
php run.php tiles/scripts/check-config 2>/dev/null

function find_string_in_code() {
	local str dir ext
	dir="$1"
	ext="$2"
	str="$3"

	find $dir \! -name 'dev-*' -a -name "$ext" -exec grep -q -- "$str" {} \; -a -not -exec grep -q "//!allow $str!" {} \; -print
}

echo -n "checking models for syntax errors ... "
for i in `ls models/*php` ; do
	RET=200
	while test "$RET" -gt 1; do
		sh -c "(php -l $i >/dev/null||exit)" 2>/dev/null
		RET=$?
		if test "$RET" -eq 255; then
			echo "ERROR parse error in $i"
			exit 1
		elif test "$RET" -gt 0; then
			#segfault crap
			echo -n "."
		fi
	done
done
echo "ok";

echo -n "checking cron.php for syntax errors ... "
RET=200
while test "$RET" -gt 1; do
	sh -c "(php -l cron.php >/dev/null||exit)" 2>/dev/null
	RET=$?
	if test "$RET" -eq 255; then
		echo "ERROR parse error in cron.php"
		exit 1
	elif test "$RET" -gt 0; then
		#segfault crap
		echo -n "."
	fi
done
echo "ok"

echo -n "checking models for static model-methods ... "
grep -E 'static[ \t]+function[ \t]+main_' models/*.php
if [ $? -gt 0 ]; then
	echo "ok"
else
	echo "ERROR: static model-method left in model!"
fi

echo -n "checking var_dump free code ..."
found=$(find_string_in_code "tiles/ models" '*php' var_dump)
if test -n "$found"; then
	echo "WARNING var_dump left in code"
	echo $found
else
	echo "ok"
fi

echo -n "checking print_r free code ..."
found=$(find_string_in_code "tiles/ models" '*php' print_r)
if test -n "$found"; then
	echo "WARNING print_r left in code"
	echo $found
else
	echo "ok"
fi

echo -n "checking dumpvars free templates ..."
found=$(find_string_in_code tmpl/ '*tpl' dumpvars)
if test -n "$found"; then
	echo "WARNING dumpvars left in code"
	echo $found
else
	echo "ok"
fi

echo -n "checking print_r free templates ..."
found=$(find_string_in_code tmpl/ '*tpl' print_r)
if test -n "$found"; then
	echo "WARNING print_r left in code"
	echo $found
else
	echo "ok"
fi

echo -n "checking sel free tiles ..."
found=$(find_string_in_code tiles/ '*php' "sel::")
if test -n "$found"; then
	echo "WARNING sel left in tiles.. why?"
	echo $found
else
	echo "ok"
fi

echo -n "checking mod free tiles ..."
found=$(find_string_in_code tiles/ '*php' "mod::")
if test -n "$found"; then
	echo "WARNING mod left in tiles.. why?"
	echo $found
else
	echo "ok"
fi

echo -n "checking for db debugs ..."
found=$(find_string_in_code models/ '*php' "->debug")
if test -n "$found"; then
	echo "WARNING ->debug left in models.. why?"
	echo $found
else
	echo "ok"
fi

echo "checking/fixing sitewide dirs / creating shadow tables..."
php run.php tiles/scripts/check-site 2>/dev/null

mkdir -p smarty/compile 2>/dev/null
chmod 777 smarty/compile 2>/dev/null
chmod 777 static/files/ 2>/dev/null

#php run.php tiles/scripts/check-pic-upload-dirs 2>/dev/null

chmod 777 static/files/* 2>/dev/null
chmod 777 static/files/*/* 2>/dev/null

echo "done"

echo -n "checking db timezone settings ..."
php run.php tiles/scripts/check-timezone 0
if test $? -ne 0; then
	echo "db timezone is off, check your settings"
	exit 1
fi
php run.php tiles/scripts/check-timezone 1
if test $? -ne 0; then
	echo "maintenance db timezone is off, check your settings"
	exit 1
fi
echo "ok"


echo -n "checking if cron.php is called from crontab... "
if crontab -l | grep -qF $PROJ_DIR'/cron.php'; then
	echo ok
else
	echo "WARNING: cron.php is not called from this user's crontab"
	echo "add the following (or equivalent) to your crontab:"
	echo "0 3 * * *     $PROJ_DIR/cron.php daily"
	echo "1 * * * *     $PROJ_DIR/cron.php hourly"
	echo "*/10 * * * *  $PROJ_DIR/cron.php hourly6"
	echo "*/15 * * * *  $PROJ_DIR/cron.php hourly4"
	echo "* * * * *     $PROJ_DIR/cron.php minute"
fi

if test "$SKIP_DBCHECK" '==' ""; then
	if test -z "$NONINTERACTIVE"; then
		if tty >/dev/null; then
			echo -n "press return to begin db checking or ^C to cancel "
			read
		fi
	fi

	echo -e "\nDB"
	php run.php tiles/scripts/init-db
	
	echo -e "\nALL DONE"

	if test "$?" -gt 0; then
		exit 1
	fi
else
	echo -e "\nALL DONE"
fi


