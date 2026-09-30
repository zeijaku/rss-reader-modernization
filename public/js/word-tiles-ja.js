/* Japanese adapter for the original shared Word Tiles rules. */
(function(window){'use strict';var game=window.RssWordTilesFactory(window.RssWordTilesJapaneseData,true);window.RssWordTilesJapanese=game.api;window.RssGameWidget.register('word_tiles_ja',game.mount);})(window);
