
# Nextcloud Sharing Path  [![ko-fi](https://www.ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/F1F51I62J)

Nextcloud app to enhance files sharing usage. Easy share, multi-use.

Now you can share your files by path format like below:

`https://youre-domain/nextcloud/apps/sharingpath/username/shared-file-stored-path`

In this way, you can use your nextcloud as CDN origin storage 🌩.

⚠️ **Attention** *Potential security risk: links could be guessed and the files in shared directories can be accessed.*


## Installation

- Install from [Nextcloud App Store](https://apps.nextcloud.com/apps/sharingpath), navigate in your Nextcloud instance to the `Apps`, in the category `Files` or `Tools` find `Sharing Path` and enable it.

- Install by yourself, download this and put to your Nextcloud instance install path `/your-nextcloud-install-path/apps/`.


## Usage

Just share your files or directories(add a share link without `Hide download` or `Password protect` and not expired if expiration date has set), then you can get the url by click `Copy Sharing Path` from more icon `···` dropdown actions menu or right click menu. 

And your can disable by uncheck `Enable sharing path` at `Settings` - `Personal` > `Sharing` - `Sharing Path`.


## Screenshots

<p align="center"><img src="https://user-images.githubusercontent.com/5813232/61992484-bc745d80-b091-11e9-84bc-005a2a6caf14.png" alt="Nextcloud Sharing Path" width="500"></p>


## TODO

- [x] Shared files(or at shared directories) can be accessed by stored path
- [x] User setting(enable|disable by user, not admin)
- [ ] Version file access
- [ ] ...


## License

[AGPL](./COPYING)
