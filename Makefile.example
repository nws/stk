# MISSING:
# db timezone check
# smarty (and other) dir existence and permission checks
# crontab check
# db diff

.PHONY: default clean

FAKE_TARGET_DIR = misc/

OK_PHP_L = $(FAKE_TARGET_DIR).ok-php-l

OK_PHPLINT = $(FAKE_TARGET_DIR).ok-phplint

OK_PHP_VERSION = $(FAKE_TARGET_DIR).ok-php-version
WANT_PHP_VERSION = 5.3.0

OK_PHP_MODULES = $(FAKE_TARGET_DIR).ok-php-modules
WANT_PHP_MODULES = curl mysqli gd
HELPER_PHP_MOD_LOADED = if (!extension_loaded($$argv[1])) { echo "ERROR: missing module {$$argv[1]}\n"; exit(1); }

OK_BADSTRINGS_PHP = $(FAKE_TARGET_DIR).ok-badstrings-php
OK_BADSTRINGS_TPL = $(FAKE_TARGET_DIR).ok-badstrings-tpl
OK_BADSTRINGS_MODELS = $(FAKE_TARGET_DIR).ok-badstrings-models
OK_BADSTRINGS_TILES = $(FAKE_TARGET_DIR).ok-badstrings-tiles

HELPER_FIND_STRING_IN_CODE = found="$$(find $(1) -exec grep -q -- '$(2)' {} \; -a -not -exec grep -q '//!allow $(2)' {} \; -print)"; \
	if [ -n "$$found" ]; then \
		echo "ERROR: $(2) in:"; \
		echo $$found; \
		exit 1; \
	fi

ALL_PHP_DIRS = tiles cfg inc models *.php
ALL_PHP = $(shell find $(ALL_PHP_DIRS) -name '*.php')
ALL_TPL = $(shell find tmpl -name '*.tpl')
ALL_MODELS = $(shell find models -name '*.php')
ALL_TILES = $(shell find tiles -name '*.php')

HELPER_XARGS = echo $(1) | tr ' ' '\n' | xargs -l1 $(2) 

default: $(OK_PHP_L) $(OK_PHPLINT) $(OK_PHP_VERSION) $(OK_PHP_MODULES) $(OK_BADSTRINGS_PHP) $(OK_BADSTRINGS_TPL) $(OK_BADSTRINGS_MODELS) $(OK_BADSTRINGS_TILES)

$(OK_PHP_L): $(ALL_PHP)
	@$(call HELPER_XARGS,$?,php -l) && touch $(OK_PHP_L)

$(OK_PHPLINT): $(ALL_PHP)
	./stk/phplint.php $? && touch $(OK_PHPLINT)

$(OK_PHP_VERSION): index.php
	@php -r 'exit(intval(version_compare(PHP_VERSION, "$(WANT_PHP_VERSION)") < 0));' \
		&& touch $(OK_PHP_VERSION) \
		|| (echo "ERROR: you need at least php version $(WANT_PHP_VERSION)" ; exit 1)

$(OK_PHP_MODULES): index.php
	@$(call HELPER_XARGS,$(WANT_PHP_MODULES),php -r '$(HELPER_PHP_MOD_LOADED)') && touch $(OK_PHP_MODULES)

$(OK_BADSTRINGS_PHP): $(ALL_PHP)
	@$(call HELPER_FIND_STRING_IN_CODE,$?,var_dump) \
		&& $(call HELPER_FIND_STRING_IN_CODE,$?,print_r) \
		&& touch $(OK_BADSTRINGS_PHP)

$(OK_BADSTRINGS_TPL): $(ALL_TPL)
	@$(call HELPER_FIND_STRING_IN_CODE,$?,print_r) \
		&& $(call HELPER_FIND_STRING_IN_CODE,$?,var_dump) \
		&& touch $(OK_BADSTRINGS_TPL)

$(OK_BADSTRINGS_MODELS): $(ALL_MODELS)
	@$(call HELPER_FIND_STRING_IN_CODE,$?,->debug) \
		&& touch $(OK_BADSTRINGS_MODELS)

$(OK_BADSTRINGS_TILES): $(ALL_TILES)
	@$(call HELPER_FIND_STRING_IN_CODE,$?,mod::) \
		&& $(call HELPER_FIND_STRING_IN_CODE,$?,sel::) \
		&& touch $(OK_BADSTRINGS_TILES)

clean:
	touch -d @0 $(OK_PHP_L) $(OK_PHPLINT) $(OK_PHP_VERSION) $(OK_PHP_MODULES) $(OK_BADSTRINGS_PHP) $(OK_BADSTRINGS_TPL) $(OK_BADSTRINGS_MODELS) $(OK_BADSTRINGS_TILES)


