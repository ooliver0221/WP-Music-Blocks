=== WP Music Blocks ===
Contributors: yourname
Tags: music, block, gutenberg, spotify, apple music, netease, album, playlist
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A beautiful Gutenberg music card block. Paste a music link and get a stylish card with album art, track info, and auto-adapted colors.

== Description ==

WP Music Blocks lets you embed beautiful, responsive music cards in your WordPress posts. Simply paste a link from any supported music platform, and the block will automatically fetch the album artwork, song title, artist name, release date, genre, and track list.

**Supported Platforms:**
- Apple Music
- Spotify
- NetEase Cloud Music (网易云音乐)
- QQ Music (QQ音乐)
- SoundCloud
- YouTube Music
- Bandcamp
- Tidal
- Deezer

**Features:**
- One-click music link embedding — just paste and go
- Automatic album cover color extraction for dynamic card backgrounds
- Single track card, album card with track list, or custom playlist card
- Customizable cover image upload
- Rounded corner, modern card design
- Click to open the original music link
- Fully responsive

**Card Types:**
1. **Single Track** — Show one song with album art, artist, and metadata
2. **Album** — Show the full track list from an album or playlist URL
3. **Custom Playlist** — Build your own track list with custom titles, artists, and durations

== Installation ==

1. Upload the `wp-music-blocks` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. In the block editor, search for "Music Card" and insert the block
4. Paste a music URL and the card will be generated automatically

= Requirements =

- WordPress 6.0 or higher
- PHP 7.4 or higher
- PHP GD library enabled (for color extraction from album art)

== Usage ==

1. Edit any post or page with the block editor (Gutenberg)
2. Click the "+" button to add a new block
3. Search for "Music Card"
4. Paste your music link (e.g., https://music.apple.com/album/123456)
5. The card will automatically fetch song/album information
6. Choose card type in the block settings sidebar:
   - Single Track: displays one song
   - Album / Track List: displays the full track list
   - Custom Playlist: create your own track list
7. Click "Refresh Data" if you want to re-fetch the music info

== Frequently Asked Questions ==

= What if my music link doesn't work? =

The plugin tries multiple methods to fetch music data (oEmbed, Open Graph meta tags, page parsing). If automatic detection fails, you can upload a cover image manually and edit the card type.

= Does this plugin play music? =

No, WP Music Blocks creates a visual card that links to the original music platform where users can listen.

= Are the colors really extracted from the album art? =

Yes! The plugin downloads the album cover, analyzes the dominant colors using the PHP GD library, and applies them dynamically to the card background.

= Can I use this with the classic editor? =

This is a Gutenberg block and works best with the block editor. It will not appear in the classic editor.

== Changelog ==

= 1.0.0 =
* Initial release
* Support for Apple Music, Spotify, NetEase, QQ Music, SoundCloud, YouTube Music, Bandcamp, Tidal, Deezer
* Automatic album cover color extraction
* Single track, album, and custom playlist card types
* Server-side rendering for optimal frontend performance
