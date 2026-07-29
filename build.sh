#!/usr/bin/env bash
#
# Builds the release tarballs.
#
# Two app ids are shipped from this single code base:
#
#   sharingpath  the original app id, kept so existing /apps/sharingpath/... URLs
#                keep working once the App Store entry is under our control
#   sharepath    an independent app id, publishable without waiting for the
#                transfer of the original entry
#
# The variant also gets its own PHP namespace. Both apps can end up installed on
# the same instance (the old sharingpath is still present on existing setups),
# and two apps declaring OCA\SharingPath\… would collide in the class loader.
#
set -euo pipefail

cd "$(dirname "$0")"

VERSION=$(sed -n 's/.*<version>\(.*\)<\/version>.*/\1/p' appinfo/info.xml)
OUT="$PWD/build"
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

mkdir -p "$OUT"

# Files that ship inside the app directory; tests and dev tooling stay out.
copy_app() {
	rsync -a \
		--exclude '.git' --exclude '.gitignore' --exclude '.travis.yml' \
		--exclude 'tests' --exclude 'phpunit.xml' --exclude 'phpunit.integration.xml' \
		--exclude 'Makefile' --exclude 'build.sh' --exclude 'build' \
		./ "$1/"
}

build_variant() {
	local app_id="$1" app_name="$2" namespace="$3"
	local dir="$WORK/$app_id/$app_id"

	mkdir -p "$dir"
	copy_app "$dir"

	if [ "$app_id" != "sharingpath" ]; then
		# The app id appears as a literal in the routes the JS calls, in the
		# APP_ID constant and in info.xml. Repository URLs contain
		# "nextcloud-sharing-path" (hyphenated) and are therefore untouched.
		find "$dir/lib" "$dir/js" "$dir/appinfo" "$dir/templates" -type f \
			-exec sed -i '' "s/sharingpath/$app_id/g" {} +
		# js/ is included here on purpose: the DOM id enableSharingPath is shared
		# between the settings template and settings.js, so both must be renamed
		# in lockstep or the settings toggle silently stops working.
		find "$dir/lib" "$dir/js" "$dir/appinfo" "$dir/templates" -type f \
			-exec sed -i '' "s/SharingPath/$namespace/g" {} +
		sed -i '' "s|<name>Sharing Path</name>|<name>$app_name</name>|" "$dir/appinfo/info.xml"
	fi

	tar czf "$OUT/$app_id-$VERSION.tar.gz" -C "$WORK/$app_id" "$app_id"
	echo "  $OUT/$app_id-$VERSION.tar.gz"
}

echo "Building version $VERSION:"
build_variant sharingpath "Sharing Path" SharingPath
build_variant sharepath "Share Path" SharePath
