(function() {
  'use strict';

  // null until loaded, then the settings object from the app
  let settings = null;

  function sharingEnabled() {
    return settings !== null &&
      (settings.enabled === 'yes' || (!settings.enabled && settings.default_enabled === 'yes'));
  }

  function currentUid() {
    // OC.getCurrentUser still exists in NC 33, the head dataset is the fallback
    if (window.OC && typeof OC.getCurrentUser === 'function') {
      const user = OC.getCurrentUser();
      if (user && user.uid) {
        return user.uid;
      }
    }
    return document.head.dataset.user || '';
  }

  function currentLanguage() {
    if (window.OC && typeof OC.getLanguage === 'function') {
      return OC.getLanguage() || 'en';
    }
    return document.documentElement.lang || 'en';
  }

  function buildSharingPath(davPath) {
    // davPath always starts with '/'
    // NC 33 removed OC.getProtocol/OC.getHost, location.origin is equivalent
    let prefix = window.location.origin + '/apps/sharingpath/';
    // admin setting
    prefix = settings.default_copy_prefix || prefix;
    prefix = prefix.endsWith('/') ? prefix : (prefix + '/');
    prefix += currentUid();
    // user setting
    prefix = settings.copy_prefix || prefix;
    prefix = prefix.endsWith('/') ? prefix.substring(0, prefix.length - 1) : prefix;

    return encodeURI(prefix + davPath);
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }

    // legacy fallback
    let dummyPath = document.createElement('textarea');
    dummyPath.value = text;
    dummyPath.setAttribute('readonly', '');
    dummyPath.style.position = 'absolute';
    dummyPath.style.left = '-9999px';
    document.body.appendChild(dummyPath);
    const selected =
      document.getSelection().rangeCount > 0        // Check if there is any content selected previously
        ? document.getSelection().getRangeAt(0)     // Store selection if found
        : false;

    dummyPath.select();
    document.execCommand('copy');
    document.body.removeChild(dummyPath);
    if (selected) {                                 // If a selection existed before copying
      document.getSelection().removeAllRanges();    // Unselect everything on the HTML document
      document.getSelection().addRange(selected);   // Restore the original selection
    }
    return Promise.resolve();
  }

  function actionLabel() {
    const lang = currentLanguage().split('-')[0];
    if (lang === 'de') {
      return 'Sharing-Pfad kopieren';
    }
    if ([ 'zh', 'ja', 'ko' ].includes(lang)) {
      return t('files', 'Copy') + t('files_sharing', 'Sharing') + t('files', 'Path');
    }
    return 'Copy sharing path';
  }

  // Nextcloud >= 28: Vue based files app, plain object matching the
  // @nextcloud/files FileAction interface placed in the shared registry.
  // Registered synchronously (this file is loaded as init script before the
  // files app bundles, which snapshot the registry at startup); visibility
  // is controlled through enabled() once the settings arrived.
  function registerModernAction() {
    const action = buildModernAction();

    // @nextcloud/files v4 (NC >= 33): version-scoped registries under
    // window._nc_files_scope; the library keeps pre-seeded structures (??=)
    window._nc_files_scope = window._nc_files_scope || {};
    const scopeKeys = Object.keys(window._nc_files_scope).filter(function(k) { return /^v\d/.test(k); });
    if (scopeKeys.length === 0) {
      scopeKeys.push('v4_0');
    }
    scopeKeys.forEach(function(key) {
      const reg = window._nc_files_scope[key] = window._nc_files_scope[key] || {};
      reg.fileActions = reg.fileActions || new Map();
      if (reg.fileActions instanceof Map && !reg.fileActions.has(action.id)) {
        reg.fileActions.set(action.id, action);
        // notify live consumers: the files app re-reads the action list on
        // this registry event, so registration also works when this script
        // happens to load after the files app bundles
        try {
          reg.registry = reg.registry || new EventTarget();
          if (typeof reg.registry.dispatchEvent === 'function') {
            reg.registry.dispatchEvent(new CustomEvent('register:action', { detail: action }));
          }
        } catch (e) {
          // best effort only
        }
      }
    });

    // @nextcloud/files v3 (NC 28 - 32): flat array registry
    window._nc_fileactions = window._nc_fileactions || [];
    if (!window._nc_fileactions.some(function(other) { return other.id === action.id; })) {
      window._nc_fileactions.push(action);
    }
  }

  // @nextcloud/files v3 (NC 28 - 32) calls enabled(nodes, view) and
  // exec(node, view, dir); v4 (NC >= 33) passes a single context object
  // ({nodes, view, folder, contents}) instead. Normalize both shapes.
  function nodesFromContext(arg) {
    if (Array.isArray(arg)) {
      return arg;
    }
    if (arg && Array.isArray(arg.nodes)) {
      return arg.nodes;
    }
    return [];
  }

  function nodeFromExecArg(arg) {
    if (arg && Array.isArray(arg.nodes)) {
      return arg.nodes[0];
    }
    return arg;
  }

  function buildModernAction() {
    return {
      id: 'copy-sharing-path',
      order: 25,
      displayName: function() {
        return actionLabel();
      },
      iconSvgInline: function() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M10.59,13.41C11,13.8 11,14.44 10.59,14.83C10.2,15.22 9.56,15.22 9.17,14.83C7.22,12.88 7.22,9.71 9.17,7.76V7.76L12.71,4.22C14.66,2.27 17.83,2.27 19.78,4.22C21.73,6.17 21.73,9.34 19.78,11.29L18.29,12.78C18.3,11.96 18.17,11.14 17.89,10.36L18.36,9.88C19.54,8.71 19.54,6.81 18.36,5.64C17.19,4.46 15.29,4.46 14.12,5.64L10.59,9.17C9.41,10.34 9.41,12.24 10.59,13.41M13.41,9.17C13.8,8.78 14.44,8.78 14.83,9.17C16.78,11.12 16.78,14.29 14.83,16.24V16.24L11.29,19.78C9.34,21.73 6.17,21.73 4.22,19.78C2.27,17.83 2.27,14.66 4.22,12.71L5.71,11.22C5.7,12.04 5.83,12.86 6.11,13.65L5.64,14.12C4.46,15.29 4.46,17.19 5.64,18.36C6.81,19.54 8.71,19.54 9.88,18.36L13.41,14.83C14.59,13.66 14.59,11.76 13.41,10.59C13,10.2 13,9.56 13.41,9.17Z"/></svg>';
      },
      enabled: function(context) {
        const nodes = nodesFromContext(context);
        return sharingEnabled() && nodes.length === 1 && nodes[0].type === 'file';
      },
      exec: function(arg) {
        const node = nodeFromExecArg(arg);
        return copyToClipboard(buildSharingPath(node.path)).then(function() {
          return true;
        }).catch(function() {
          return false;
        });
      },
    };
  }

  // Nextcloud <= 27: legacy file actions API
  function registerLegacyAction() {
    OCA.Files.fileActions.registerAction({
      name: 'copy-sharing-path',
      displayName: actionLabel(),
      mime: 'file',
      permissions: OC.PERMISSION_READ,
      iconClass: 'icon-public',
      actionHandler: function(filename, context) {
        const davPath = (context.dir === '/' ? '' : context.dir) + '/' + filename;
        copyToClipboard(buildSharingPath(davPath));
      },
    });
  }

  registerModernAction();

  function init() {
    const url = (window.OC && typeof OC.generateUrl === 'function')
      ? OC.generateUrl('/apps/sharingpath/settings')
      : '/index.php/apps/sharingpath/settings';
    fetch(url, {
      headers: {
        'requesttoken': (window.OC && OC.requestToken) || document.head.dataset.requesttoken || '',
        'Accept': 'application/json',
      },
    }).then(function(response) {
      return response.json();
    }).then(function(data) {
      settings = data || {};
      if (sharingEnabled() &&
        window.OCA && OCA.Files && OCA.Files.fileActions && OCA.Files.fileActions.registerAction) {
        registerLegacyAction();
      }
    }).catch(function(error) {
      console.warn('sharingpath: could not load settings', error);
    });
  }

  if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
