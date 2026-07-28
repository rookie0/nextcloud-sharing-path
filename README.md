
# Nextcloud Sharing Path — maintained fork

> **This is a maintained fork of [rookie0/nextcloud-sharing-path](https://github.com/rookie0/nextcloud-sharing-path)
> with compatibility fixes for Nextcloud 25 – 34, tested against Nextcloud 33.0.6 (Hub 26 Winter).**
> Upstream has been unmaintained since 2022 and breaks with an `Internal Server Error`
> on Nextcloud 33 (it still uses the private `OC_Response` class that was removed from the server).
> All credit for the original app goes to [Rookie0](https://github.com/rookie0).

Nextcloud app to enhance files sharing usage. Easy share, multi-use.

Share your files by path format like below:

`https://your-domain/nextcloud/apps/sharingpath/username/shared-file-stored-path`

In this way, you can use your Nextcloud as CDN origin storage 🌩.

⚠️ **Attention** *Potential security risk: links could be guessed and the files in shared directories can be accessed.*


## What this fork changes (v0.7.0)

- Fixes the `Internal Server Error` on every download since Nextcloud 33
  (`OC_Response` was removed from the server core)
- Downloads are streamed through the public OCP API (`IRootFolder` / `File::fopen`)
  instead of private server internals, so future core refactorings are far less likely
  to break the app again; HTTP `Range` and multipart range requests keep working
- Restores the `Copy sharing path` file action in the Vue based files app
  (Nextcloud 28+, including the `@nextcloud/files` v4 registry used since Nextcloud 33) —
  no build step required, plain JS
- Replaces the removed `OC.getProtocol()` / `OC.getHost()` / `OC_User` /
  legacy event APIs and fixes PHP 8.2+ deprecations
- Errors are logged to `nextcloud.log` instead of producing an anonymous 500 page

See [CHANGELOG](CHANGELOG.md) for details. Version 0.6.0 is intentionally skipped to stay
above the version used by the (also broken on NC 33) AnotherFoxGuy fork, since Nextcloud
refuses app downgrades.


## Installation

No build step needed — the app runs as-is.

- Download `sharingpath-x.y.z.tar.gz` from the
  [releases page](https://github.com/arundne/nextcloud-sharing-path/releases) and extract it
  into your Nextcloud `apps/` (or `custom_apps/`) directory:

  ```bash
  tar xzf sharingpath-x.y.z.tar.gz -C /path/to/nextcloud/custom_apps/
  ```

- Or clone this repository into `apps/sharingpath`.

Then enable `Sharing Path` at `Apps` > `Your apps` (or `occ app:enable sharingpath`).

The upstream [Nextcloud App Store entry](https://apps.nextcloud.com/apps/sharingpath) still
ships 0.4.4, which only supports Nextcloud ≤ 24 — use the releases from this fork instead.


## Usage

Check `Enable sharing path` at `Settings` > `Sharing` first.

Then just share your files or directories (add a share link without `Hide download` or
`Password protect` and not expired if an expiration date has been set). You can copy the
URL via `Copy sharing path` / `Sharing-Pfad kopieren` in the file's `···` actions menu.

There are some settings in `Settings` > `Sharing` (Administration & Personal) > `Sharing Path`
you may want to take a look at.


## Screenshots

<p align="center"><img src="https://user-images.githubusercontent.com/5813232/103185230-6e066680-48f6-11eb-852a-b51002e6adba.png" alt="Nextcloud Sharing Path" width="500"></p>
<p align="center"><img src="https://user-images.githubusercontent.com/5813232/103185234-7363b100-48f6-11eb-8c67-cb9a587bd45a.png" alt="Nextcloud Sharing Path" width="500"></p>


## Disclaimer

**AI-assisted development.** The Nextcloud 25–34 compatibility changes in this fork
(v0.7.0 and later) were developed and tested with substantial assistance from an AI coding
agent (Claude Code by Anthropic). Every change was verified against a real Nextcloud 33.0.6
instance (download matrix including range requests, files UI action, settings pages), but as
with any software: review the code yourself before deploying it to critical environments.

**No warranty, no liability.** This software is provided **“as is”**, without warranty of any
kind, express or implied. To the maximum extent permitted by applicable law, the authors and
maintainers of this fork accept **no liability** for any damages arising from its use —
including, without limitation, data loss or the unintended public exposure of files. Use at
your own risk. The governing terms are sections 15 (“Disclaimer of Warranty”) and 16
(“Limitation of Liability”) of the [AGPL license](./COPYING).

**Security note.** By design, everything below a shared folder (or a configured sharing
folder) becomes reachable by guessable public URLs without further authentication. Decide
consciously where this app is appropriate.


## Changelog

See [CHANGELOG](CHANGELOG.md)


## License

[AGPL](./COPYING)
