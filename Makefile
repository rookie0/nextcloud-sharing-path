.PHONY: all build appstore source dist test clean
all: build
build appstore source dist:
	./build.sh

test:
	php tests/unit.php
	node --test tests/script.test.cjs
	python3 tests/packaging.py

clean:
	rm -rf build
