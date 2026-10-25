# Nextcloud Sharing Path

Create public file URLs using a user's file path:

`https://cloud.example/nextcloud/index.php/apps/sharingpath/alice/public/photo.jpg`

The app ID remains **`sharingpath`**. Existing settings and URLs are retained. Compatibility and settings authorization fixes were contributed by [arundne](https://github.com/arundne). This repository remains [rookie0/nextcloud-sharing-path](https://github.com/rookie0/nextcloud-sharing-path).

## Access rules

Enable Sharing Path in **Settings → Sharing**, then either:

- Create a public link for a file or a parent folder, with read permission and no password, expiration or download restriction.
- Explicitly configure a **sharing folder**. Every file below that folder becomes public without requiring a share. This setting intentionally bypasses link-share restrictions for that folder: do not put confidential files there.

URLs are guessable. Sharing Path is unsuitable for files requiring authentication or password protection. Outside a configured sharing folder, protected, expired, hidden-download and upload-only links do not grant access. If multiple links exist on a file or an ancestor, any restricted link denies path access. Disabled users, directories and traversal paths cannot be downloaded.

Global defaults can only be changed by administrators. Users can change their own settings. Responses use `Cache-Control: no-store` to prevent previously cached responses from outliving revoked access.

The **Copy sharing path** file action supports subdirectory installations and file names containing spaces, Unicode, `#`, `?` and `%`. A custom HTTP(S) copy prefix can be configured for a reverse proxy; it must route to the same application and cannot contain credentials, a query or a fragment.

## Install and upgrade

This branch targets Nextcloud **25–34**. Nextcloud 24 and earlier are not supported by this release. See the PR checks and validation notes below before deployment.

Build from a Git checkout with Python 3:

```sh
./build.sh
```

The output is `build/sharingpath-0.7.3.tar.gz`. Extract it into the Nextcloud `custom_apps` directory so the resulting path is `custom_apps/sharingpath/appinfo/info.xml`, then enable `sharingpath`. When upgrading an existing installation, replace the application files, preserve Nextcloud's database/configuration/data, and run `php occ upgrade` as the web server user.

The archive contains only tracked application files and documentation. Local keys, certificates, environment files, development tools and tests are excluded. Do not use the generated source archive from GitHub as an App Store installation package. An App Store release also needs a valid **`sharingpath`** signing certificate; `sharepath` is a different app ID. Signing keys must remain outside the checkout.

No packages or certificates are automatically published by the build or CI. Application ownership on the App Store is independent of ownership of this GitHub repository.

## Validation

```sh
php tests/unit.php
node --test tests/script.test.cjs
python3 tests/packaging.py
```

PHP checks cover settings authorization, bounded byte-range parsing, inaccessible streams and non-seekable full-response fallback. JavaScript checks cover action interfaces and URL encoding. Packaging checks inject dummy secret files and verify exclusions, reproducibility and symlink rejection.

The GitHub Actions workflow installs the **built archive** into disposable Nextcloud 25–34 instances and exercises HTTP GET/HEAD, byte ranges, protected shares, traversal rejection, authenticated settings and CSRF rejection. Fixtures in `tests/fixtures.php` are for disposable test instances only; never run them on a production server.

A passing matrix covers these scenarios on the recorded Nextcloud/PHP versions. It does not certify every external storage backend, third-party app or reverse proxy configuration. Non-seekable storage is additionally exercised with an isolated stream fixture. Browser checks are recorded in the PR.

## License

AGPL-3.0-or-later; see [COPYING](COPYING). The original project and contributor work retain their respective attribution. The software is provided without warranty.
