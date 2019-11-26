
# Nextcloud Sharing Path

Nextcloud app to enhance files sharing usage. Now you can share your files by path format.

eg: `https://youre-domain/nextcloud/apps/sharingpath/username/shared-file-stored-path`

In this way, you can use your nextcloud as CDN origin storage 🌩.

⚠️ **Attention** *Potential security risk: links could be guessed and the files in shared directories can be accessed.*


## Installation

- Install from [Nextcloud App Store](https://apps.nextcloud.com/apps/sharingpath), navigate in your Nextcloud instance to the `Apps`, in the category `Files` or `Tools` find `Sharing Path` and enable it.

- Install by yourself, download this and put to your Nextcloud instance install path `/your-nextcloud-install-path/apps/`.


## Usage

Just share your files or directories, then you can access your file by `https://youre-domain/nextcloud/apps/sharingpath/username/shared-file-stored-path`.

Share your files or directories by share icon or click `Share` from files right click dropdown actions menu, then add a share link without `Hide download` or `Password protect` and not expired if expiration date has set, then click `Copy Sharing Path` from more icon `···` dropdown actions menu or right click menu, and the copied url is the direct link can be accessed. 



## Screenshots

![Nextcloud Sharing Path](https://user-images.githubusercontent.com/5813232/61992484-bc745d80-b091-11e9-84bc-005a2a6caf14.png)


## TODO

- [x] Shared files(or at shared directories) can be accessed by stored path
- [ ] User setting(enable|disable by user, not admin)
- [ ] Version file access
- [ ] ...


## License

[AGPL](./COPYING)
