
# Change logs


## 0.7.0 - 2026-07-28

Compatibility release for Nextcloud 25 - 34, tested against Nextcloud 33.0.6 (Hub 26 Winter).
First release of the [arundne maintenance fork](https://github.com/arundne/nextcloud-sharing-path).
Version 0.6.0 is skipped intentionally: the AnotherFoxGuy fork already uses it, and Nextcloud
refuses app downgrades, so this release must sort higher.

- Fix `Internal Server Error` on every download since Nextcloud 33: the removed private class
  `OC_Response` was still used for the `Content-Length` header
- Stream files through the public OCP API (`IRootFolder` / `File::fopen`) instead of the private
  `OC_Util::setupFS` / `OC\Files\Filesystem` / `View` internals, so future core refactorings
  no longer break the download endpoint (range / multipart range requests still supported)
- Catch `\Throwable` instead of `\Exception` so PHP engine errors are logged to `nextcloud.log`
  instead of producing an anonymous 500 page
- Add the modern security attributes (`#[PublicPage]`, `#[NoCSRFRequired]`, ...) alongside the
  deprecated annotations
- Restore the `Copy sharing path` file action on Nextcloud 28+ (Vue files app): register through
  the `@nextcloud/files` v3 array registry and the v4 scoped registry (Nextcloud 33+), loaded as
  init script; German label `Sharing-Pfad kopieren`
- Replace removed `OC.getProtocol()` / `OC.getHost()` with `location.origin`; clipboard now uses
  `navigator.clipboard` with a legacy fallback
- Replace legacy `OC_User::getUser()` with `IUserSession` in the personal settings
- Load the files integration via the typed `LoadAdditionalScriptsEvent` (the legacy string event
  is kept for old releases)
- Fix deprecated `${var}` string interpolation (PHP 8.2+)


## 0.4.4(nightly) - 2022-01-21

- Add debug log for [#39](https://github.com/rookie0/nextcloud-sharing-path/issues/39)
- Function `str_starts_with` polyfill
- Nextcloud app store description


## 0.4.3 - 2021-09-23

- Fix logged in user request error [#23](https://github.com/rookie0/nextcloud-sharing-path/issues/23), [#31](https://github.com/rookie0/nextcloud-sharing-path/issues/31)
- Change user default enable setting behavior, now you must explicitly enable sharing path at `Setting` > `Sharing` first, then you can use this app to access your sharing files


## 0.4.1(nightly) - 2021-04-28

- Fix new user share access got 403 by default settings [#33](https://github.com/rookie0/nextcloud-sharing-path/issues/33)


## 0.4.0(nightly) - 2020-12-28

- Add sharing folder setting [#16](https://github.com/rookie0/nextcloud-sharing-path/issues/16), ❗⚠️❗️️all files in this folder can be accessed without share first
- Add copy prefix setting [#28](https://github.com/rookie0/nextcloud-sharing-path/issues/28)
- Add admin default setting, ❗⚠️❗personal setting will extend the default settings if you do not set or leave it blank


## 0.3.0 - 2020-11-12

- 20 compatibility
- Change default enable status to disable [#26](https://github.com/rookie0/nextcloud-sharing-path/issues/26), now you must goto `Settings > Personal > Sharing > Sharing Path` check this before you can use


## 0.2.5 - 2020-07-21 
 
- Fix url format [#24](https://github.com/rookie0/nextcloud-sharing-path/issues/24)


## 0.2.4 - 2020-06-13

- Fix response headers by exit [#17](https://github.com/rookie0/nextcloud-sharing-path/issues/17)


## 0.2.3 - 2020-06-10

- Fix [#22](https://github.com/rookie0/nextcloud-sharing-path/issues/22) [#20](https://github.com/rookie0/nextcloud-sharing-path/issues/20)


## 0.2.2 - 2020-04-27

- Change do not check file is shared [#14](https://github.com/rookie0/nextcloud-sharing-path/issues/22) [#20](https://github.com/rookie0/nextcloud-sharing-path/issues/14)


## 0.2.1 - 2020-04-19

- Fix readfile with exit
- Add user settings


## 0.1.2 - 2020-03-17

- Add http range support [#5](https://github.com/rookie0/nextcloud-sharing-path/issues/22) [#20](https://github.com/rookie0/nextcloud-sharing-path/issues/5)
- 18 compatibility


## 0.0.3 - 2019-11-14

- Update copy sharing path [#5](https://github.com/rookie0/nextcloud-sharing-path/issues/22) [#20](https://github.com/rookie0/nextcloud-sharing-path/issues/5)
- Update readme
- 17 compatibility

## 0.0.1 - 2019-07-29

- First release
