/* Article Actions: URL copy and X Web Intent; no server mutation. */
(function ($, window, document) {
    'use strict';
    if (window.IGuguruDashboardArticleShare) { return; }

    function showNotice(message, type, duration) {
        return window.IGuguruDashboardCore.showNotice(message, type, duration);
    }
    function closeArticleActionsMenu(returnFocus) {
        return window.IGuguruDashboardArticleMenu.closeArticleActionsMenu(returnFocus);
    }

    function legacyCopyText(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);
        var copied = false;
        try {
            copied = typeof document.execCommand === 'function' && document.execCommand('copy') === true;
        } catch (ignore) {
            copied = false;
        }
        document.body.removeChild(textarea);
        return copied;
    }

    function copyArticleUrl($button) {
        var articleUrl = String($('#articleActionsMenu').data('article-url') || '');
        closeArticleActionsMenu(true);
        if (articleUrl === '') {
            showNotice('コピーする記事URLを確認出来ませんでした', 'danger');
            return;
        }

        function copied() {
            showNotice('記事URLをコピーしました', 'success', 2500);
        }
        function fallback() {
            if (legacyCopyText(articleUrl)) {
                copied();
            } else {
                showNotice('記事URLをコピー出来ませんでした', 'danger');
            }
        }

        if (window.isSecureContext && window.navigator.clipboard && typeof window.navigator.clipboard.writeText === 'function') {
            try {
                window.navigator.clipboard.writeText(articleUrl).then(copied, fallback);
                return;
            } catch (ignore) {
                fallback();
                return;
            }
        }
        fallback();
    }

    function articleShareTitle(title) {
        var characters = Array.from(String(title || '').trim());
        if (characters.length <= 200) {
            return characters.join('');
        }
        return characters.slice(0, 199).join('') + '…';
    }

    function openArticleOnX() {
        var $menu = $('#articleActionsMenu');
        var articleUrl = String($menu.data('article-url') || '');
        var articleTitle = articleShareTitle(String($menu.data('article-title') || ''));
        closeArticleActionsMenu(true);
        if (articleUrl === '') {
            showNotice('Xへ投稿する記事URLを確認出来ませんでした', 'danger');
            return;
        }

        var intentUrl = 'https://x.com/intent/post?text=' + encodeURIComponent(articleTitle) + '&url=' + encodeURIComponent(articleUrl);
        var popup = null;
        try {
            popup = window.open('', '_blank');
            if (popup) {
                popup.opener = null;
                popup.location.href = intentUrl;
            }
        } catch (ignore) {
            popup = null;
        }
        if (!popup) {
            showNotice('Xの投稿画面を開けませんでした。ポップアップ設定を確認してください', 'danger');
            return;
        }
        showNotice('Xの投稿画面を開きました', 'success', 2500);
    }

    window.IGuguruDashboardArticleShare = {
        copyArticleUrl: copyArticleUrl,
        openArticleOnX: openArticleOnX
    };
})(jQuery, window, document);
