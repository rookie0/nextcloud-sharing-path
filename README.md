# Nextcloud Sharing Path

Create public file URLs using a user's file path:

`https://cloud.example/nextcloud/index.php/apps/sharingpath/alice/public/photo.jpg`

The app ID remains **`sharingpath`**. Existing settings and URLs are retained. Compatibility and settings authorization fixes were contributed by [arundne](https://github.com/arundne). This repository remains [rookie0/nextcloud-sharing-path](https://github.com/rookie0/nextcloud-sharing-path).

## Access rules

Enable Sharing Path in **Settings → Sharing**, then either:

- Create a public link for a file or a parent folder, with read permission and no password or download restriction, and an expiration date that has not passed.
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
node --test tests/*.test.cjs
python3 tests/packaging.py
```

PHP checks cover settings authorization, bounded byte-range parsing, inaccessible streams and non-seekable full-response fallback. JavaScript checks cover action interfaces and URL encoding. Packaging checks inject dummy secret files and verify exclusions, reproducibility and symlink rejection.

The GitHub Actions workflow installs the **built archive** into disposable Nextcloud 25–34 instances and exercises HTTP GET/HEAD, byte ranges, protected shares, traversal rejection, authenticated settings and CSRF rejection. Fixtures in `tests/fixtures.php` are for disposable test instances only; never run them on a production server.

A passing matrix covers these scenarios on the recorded Nextcloud/PHP versions. It does not certify every external storage backend, third-party app or reverse proxy configuration. Non-seekable storage is additionally exercised with an isolated stream fixture. Browser checks are recorded in the PR.

## License

AGPL-3.0-or-later; see [COPYING](COPYING). The original project and contributor work retain their respective attribution. The software is provided without warranty.

## Configuration examples and troubleshooting

| Setting | Example | Meaning |
| --- | --- | --- |
| Administrator copy prefix | `https://cloud.example/nextcloud/index.php/apps/sharingpath` | The user's ID is appended automatically. |
| Personal copy prefix | `https://cdn.example/apps/sharingpath/alice` | Complete prefix including the user ID; overrides the administrator default. |
| Sharing folder | `public/photos` | Relative to each user's own Files root, not a server filesystem path. |

Personal values override administrator defaults; an empty personal folder/prefix inherits the default. A personal **disabled** switch overrides an enabled administrator default. The administrator's settings panel changes instance-wide defaults; the personal panel changes only the current account.

- A future share expiration is supported; access stops when that date is reached. A file or its parent folder must have an eligible public link unless it is inside the explicitly configured sharing folder.
- The copy action is for files. It does not create a share, and a URL is not proof that the file is public. Denied or missing files return HTTP 404 with a generic explanation.
- `/remote.php/dav/files/...` is a WebDAV URL and normally requires credentials. Use **Copy sharing path**, whose URL contains `/apps/sharingpath/<user>/...`.
- For Snap, a subdirectory install or a proxy without pretty-URL rewriting, keep the `index.php` and subdirectory parts produced by `OC.generateUrl`. Clear old custom copy prefixes before retesting. An Apache-branded 404 without an application response means the request did not reach the app; check the proxy/rewrite rules.
- Anonymous `curl`/`wget` requests use the same access checks as a browser. Quote URLs containing `&`, `?` or shell metacharacters and use the encoded URL from the action. A browser's logged-in WebDAV URL is not an anonymous download link.
- Moving Nextcloud's primary storage to S3 requires migrating the stored objects. Sharing Path reads through Nextcloud's file API; it cannot recover unmigrated local objects and will not fall back to arbitrary filesystem paths.
- For token-based links without a username/path, use Nextcloud's own public-share download route (`/s/<token>/download`) for a **file** share. Folder shares download an archive. Password and expiration restrictions remain controlled by Nextcloud. This app does not reinterpret filenames as share tokens.

### Embedding on another website

Image/video embedding and iframe embedding have different browser rules. Inspect the headers returned by the download URL. In particular, the Apache configuration shipped with Nextcloud uses `Header always set X-Frame-Options "SAMEORIGIN"`; an app cannot remove that server-level rule with PHP. `X-Frame-Options: ALLOW` is not a valid solution.

If you intentionally allow a trusted site to frame these public files, use the explicit `index.php` URL (for example, `/index.php/apps/sharingpath/alice/public/file.txt`). An administrator can apply this **route-scoped** override in Apache's virtual-host configuration, substituting the actual trusted origin and installation prefix:

```apache
<LocationMatch "^/index\.php/apps/sharingpath/(?!settings(?:/|$))[^/]+/.+">
    Header onsuccess unset X-Frame-Options
    Header always unset X-Frame-Options
    Header onsuccess unset Content-Security-Policy
    Header always set Content-Security-Policy "sandbox; default-src 'none'; frame-ancestors 'self' https://embed.example;"
</LocationMatch>
```

Apache's internal rewriting of shortened URLs can change which location rules apply; the explicit `index.php` route above was tested with the Nextcloud Apache image. The exception must not match `/apps/sharingpath/settings`, Nextcloud login pages or the rest of Nextcloud. Keep `X-Content-Type-Options: nosniff`. A front proxy may add its own headers and needs an equivalent narrow rule. Verify both the public-file response and an authenticated settings response after reloading the web server. Do not remove frame protection globally. The sandbox intentionally does not enable scripts or same-origin access in framed documents.
