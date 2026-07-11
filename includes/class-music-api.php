<?php
/**
 * Music API - Parse URLs from Apple Music, QQ Music, and NetEase Cloud Music.
 *
 * @package WPMusicBlocks
 */

namespace WPMusicBlocks;

class Music_API {

    private static $last_errors = [];

    public static function get_last_errors() {
        return self::$last_errors;
    }

    public static function fetch($url, $force_platform = '') {
        self::$last_errors = [];
        $url      = urldecode($url);
        $platform = $force_platform ?: self::detect_platform($url);

        $base = [
            'type'        => 'single',
            'platform'    => $platform,
            'url'         => $url,
            'title'       => '',
            'artist'      => '',
            'album'       => '',
            'coverUrl'    => '',
            'releaseDate' => '',
            'genre'       => '',
            'tracks'      => [],
        ];

        switch ($platform) {
            case 'apple_music':
                $data = self::fetch_apple_music($url);
                break;
            case 'netease':
                $data = self::fetch_netease($url);
                break;
            case 'qqmusic':
                $data = self::fetch_qqmusic($url);
                break;
            default:
                // Try each and use first success
                $data = self::fetch_apple_music($url);
                if (empty($data['title'])) $data = self::fetch_netease($url);
                if (empty($data['title'])) $data = self::fetch_qqmusic($url);
                break;
        }

        if (!empty($data['detectedType'])) {
            $base['type'] = $data['detectedType'];
            unset($data['detectedType']);
        } else {
            $base['type'] = self::detect_type_from_url($url);
        }

        return array_merge($base, $data);
    }

    // ═══════════════════════════════════════════════════
    // Platform detection
    // ═══════════════════════════════════════════════════

    private static function detect_platform($url) {
        $host = strtolower(wp_parse_url($url, PHP_URL_HOST) ?: '');
        if (strpos($host, 'music.apple.com') !== false || strpos($host, 'itunes.apple.com') !== false) return 'apple_music';
        if (strpos($host, 'y.qq.com') !== false || strpos($host, 'i.y.qq.com') !== false) return 'qqmusic';
        if (strpos($host, 'music.163.com') !== false || strpos($host, '163cn.tv') !== false) return 'netease';
        return 'unknown';
    }

    private static function detect_type_from_url($url) {
        $path = strtolower(wp_parse_url($url, PHP_URL_PATH) ?: '');
        if (preg_match('#^/.*?/(album|playlist)/#i', $path)) return 'album';
        return 'single';
    }

    // ═══════════════════════════════════════════════════
    // Apple Music — HTML meta tags + JSON-LD schema
    // ═══════════════════════════════════════════════════

    private static function fetch_apple_music($url) {
        $result = [
            'title' => '', 'artist' => '', 'album' => '',
            'coverUrl' => '', 'releaseDate' => '', 'genre' => '',
            'tracks' => [], 'detectedType' => '',
        ];

        // Library URLs require authentication — server cannot access
        if (strpos($url, '/library/') !== false) {
            self::$last_errors[] = 'Apple Music library URL requires login (personal library). Use a public Apple Music URL instead (e.g. music.apple.com/cn/album/...).';
            return $result;
        }

        $html = self::get_page($url);
        if (empty($html)) return $result;

        // 1. JSON-LD schema (most complete data)
        $schema = self::parse_jsonld($html);
        if ($schema) {
            $result['title']       = $schema['name'] ?? '';
            $result['coverUrl']    = $schema['image'] ?? '';
            $result['releaseDate'] = self::extract_date($schema['datePublished'] ?? '');

            // Artist
            $artists = [];
            if (!empty($schema['audio']['byArtist'])) {
                foreach ($schema['audio']['byArtist'] as $a) $artists[] = $a['name'] ?? '';
            }
            if (!empty($schema['byArtist']['name'])) $artists[] = $schema['byArtist']['name'];
            $result['artist'] = implode(' / ', array_filter($artists));

            // Album name
            if (!empty($schema['audio']['inAlbum']['name'])) {
                $result['album'] = $schema['audio']['inAlbum']['name'];
            }

            // Genre (MusicRecording: nested, MusicAlbum: top-level)
            if (!empty($schema['audio']['genre'])) {
                $result['genre'] = is_array($schema['audio']['genre'])
                    ? implode(' / ', $schema['audio']['genre']) : $schema['audio']['genre'];
            } elseif (!empty($schema['genre'])) {
                $result['genre'] = is_array($schema['genre'])
                    ? implode(' / ', $schema['genre']) : $schema['genre'];
            }

            // Type from schema @type
            $stype = strtolower($schema['@type'] ?? '');
            if (strpos($stype, 'musicalbum') !== false) $result['detectedType'] = 'album';

            // Extract tracks from schema (for albums)
            $track_list = $schema['track']['itemListElement'] ?? $schema['tracks'] ?? null;
            if (is_array($track_list)) {
                $tracks = [];
                foreach ($track_list as $item) {
                    $track = $item['item'] ?? $item;
                    if (!empty($track['name'])) {
                        $duration_raw = $track['duration'] ?? '';
                        $duration_sec = 0;
                        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration_raw, $dm)) {
                            $duration_sec = intval($dm[1] ?? 0) * 3600 + intval($dm[2] ?? 0) * 60 + intval($dm[3] ?? 0);
                        }
                        $tracks[] = [
                            'title'    => $track['name'],
                            'artist'   => $result['artist'] ?? '',
                            'duration' => $duration_sec > 0 ? sprintf('%d:%02d', floor($duration_sec / 60), $duration_sec % 60) : '',
                            'url'      => $track['url'] ?? $track['@id'] ?? $url,
                        ];
                    }
                }
                if (!empty($tracks)) {
                    $result['tracks'] = $tracks;
                    $result['detectedType'] = 'album';
                }
            }
        }

        // 2. Meta tags (Apple-specific + Open Graph)
        $meta = self::parse_meta($html);

        if (empty($result['title']) && !empty($meta['apple:title'])) {
            $result['title'] = $meta['apple:title'];
        }
        if (!empty($meta['music:release_date'])) {
            $result['releaseDate'] = self::extract_date($meta['music:release_date']);
        }

        // OG fallbacks
        if (empty($result['title']) && !empty($meta['og:title'])) {
            $result['title'] = self::parse_apple_og_title($meta['og:title']);
        }
        if (empty($result['coverUrl']) && !empty($meta['og:image:secure_url'])) {
            $result['coverUrl'] = $meta['og:image:secure_url'];
        } elseif (empty($result['coverUrl']) && !empty($meta['og:image'])) {
            $result['coverUrl'] = $meta['og:image'];
        }
        if (empty($result['releaseDate']) && !empty($meta['og:description'])) {
            // "歌曲 · 2006年 · 时长 4:30"
            if (preg_match('/(\d{4})\s*年/', $meta['og:description'], $ym)) {
                $result['releaseDate'] = $ym[1];
            }
        }
        if (!empty($meta['og:type']) && $meta['og:type'] === 'music.album') {
            $result['detectedType'] = 'album';
        }

        // Artist from og:title if missing: "Apple Music 上陶喆的歌曲《每一面都美》"
        if (empty($result['artist']) && !empty($meta['og:title'])) {
            if (preg_match('/上\s*(.+?)\s*的(?:歌曲|专辑)/u', $meta['og:title'], $am)) {
                $result['artist'] = trim($am[1]);
            }
        }
        // Artist from description: "在 Apple Music 上欣赏陶喆的《每一面都美》"
        if (empty($result['artist'])) {
            $desc = $meta['description'] ?? $meta['apple:description'] ?? '';
            if (preg_match('/欣赏\s*(.+?)\s*的[《「]/u', $desc, $dm)) {
                $result['artist'] = trim($dm[1]);
            }
        }

        // 3. Page title: "歌曲名 - 由歌手演唱 - Apple Music" or "歌曲名 - 歌手 on Apple Music"
        if ((empty($result['title']) || empty($result['artist'])) && preg_match('/<title>([^<]+)<\/title>/', $html, $tm)) {
            $title = html_entity_decode($tm[1], ENT_QUOTES, 'UTF-8');
            $title = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}\x{202A}\x{202C}]/u', '', $title);
            $title = trim($title);

            // Chinese: "歌曲名 - 由歌手演唱 - Apple Music"
            if (preg_match('/^(.+?)\s*[-–—]\s*由(.+?)演唱\s*[-–—]/u', $title, $pm)) {
                if (empty($result['title'])) $result['title'] = trim($pm[1]);
                if (empty($result['artist'])) $result['artist'] = trim($pm[2]);
            }
            // English: "Song - Artist on Apple Music"
            elseif (preg_match('/^(.+?)\s*[-–—]\s*(.+?)\s+on\s+Apple\s*Music/i', $title, $pm2)) {
                if (empty($result['title'])) $result['title'] = trim($pm2[1]);
                if (empty($result['artist'])) $result['artist'] = trim($pm2[2]);
            }
        }

        // 4. Clean cover URL to get 600px version
        if (!empty($result['coverUrl'])) {
            $result['coverUrl'] = preg_replace(
                ['/\d+x\d+(bb|bf|wp)-\d+\.jpg/i', '/\d+x\d+(bb|bf|wp)\.jpg/i'],
                ['600x600bb.jpg', '600x600bb.jpg'],
                $result['coverUrl']
            );
        }

        // Ensure at least 1 track entry
        if (empty($result['tracks']) && !empty($result['title'])) {
            $result['tracks'][] = [
                'title' => $result['title'], 'artist' => $result['artist'],
                'duration' => '', 'url' => $url,
            ];
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════
    // NetEase Cloud Music — meta tags + page title
    // ═══════════════════════════════════════════════════

    private static function fetch_netease($url) {
        $result = [
            'title' => '', 'artist' => '', 'album' => '',
            'coverUrl' => '', 'releaseDate' => '', 'genre' => '',
            'tracks' => [], 'detectedType' => '',
        ];

        // Normalize hash-routing URLs: /#/song?id=X → /song?id=X
        $url = preg_replace('/\/#\/(song|album|playlist)\?/', '/$1?', $url);

        // Extract song/album ID from URL
        $song_id = '';
        if (preg_match('/[?&]id=(\d+)/', $url, $m)) {
            $song_id = $m[1];
        }

        $html = self::get_page($url);

        if (!empty($html)) {
            $meta = self::parse_meta($html);

            // Simplified OG tags (NetEase uses clean format)
            if (!empty($meta['og:title'])) $result['title'] = $meta['og:title'];
            if (!empty($meta['og:image'])) $result['coverUrl'] = $meta['og:image'];

            // NetEase has og:music:artist and og:music:album
            if (!empty($meta['og:music:artist'])) $result['artist'] = $meta['og:music:artist'];
            if (!empty($meta['og:music:album']))  $result['album']  = $meta['og:music:album'];

            // Description: "歌曲名《XXX》，别名《YYY》，由 周杰伦 演唱，收录于《范特西》专辑中"
            $desc = $meta['description'] ?? $meta['og:description'] ?? '';
            if ($desc) {
                if (empty($result['artist']) && preg_match('/由\s*(.+?)\s*演唱/u', $desc, $dm)) {
                    $result['artist'] = trim($dm[1]);
                }
                if (empty($result['album']) && preg_match('/收录于[《「](.+?)[》」]/u', $desc, $am)) {
                    $result['album'] = trim($am[1]);
                }
                if (empty($result['title']) && preg_match('/歌曲名[《「](.+?)[》」]/u', $desc, $tm)) {
                    $result['title'] = trim($tm[1]);
                }
            }

            // Page title: "歌曲名（别名） - 歌手名 - 单曲/专辑 - 网易云音乐"
            if (preg_match('/<title>([^<]+)<\/title>/', $html, $tm)) {
                $title = html_entity_decode($tm[1], ENT_QUOTES, 'UTF-8');
                $title = trim($title);
                $parts = explode(' - ', $title);

                // Always use first part as song name
                if (empty($result['title']) && !empty($parts[0])) {
                    // Strip parenthetical alias: "爸，我回来了（Dad I'm Back）"
                    $result['title'] = trim(preg_replace('/[（(][^)）]*[)）]/u', '', $parts[0]));
                }

                // Artist is always parts[1] (second part)
                if (empty($result['artist']) && count($parts) >= 2) {
                    $candidate = trim($parts[1]);
                    if (!preg_match('/^(单曲|专辑|EP|Single|Album)$/iu', $candidate)) {
                        $result['artist'] = $candidate;
                    }
                }

                // Type detection from title
                if (count($parts) >= 3) {
                    $type_part = trim($parts[count($parts) - 2]);
                    if (preg_match('/^(单曲|Single)$/iu', $type_part)) {
                        $result['detectedType'] = 'single';
                    } elseif (preg_match('/^(专辑|Album)$/iu', $type_part)) {
                        $result['detectedType'] = 'album';
                    }
                }
            }

            // og:type can also indicate type
            if (!empty($meta['og:type']) && $meta['og:type'] === 'music.album') {
                $result['detectedType'] = 'album';
            }

            // Clean cover URL
            if (!empty($result['coverUrl'])) {
                $result['coverUrl'] = preg_replace('/\?param=\d+x\d+/', '?param=300x300', $result['coverUrl']);
            }
        } // end if (!empty($html))

        // Fallback: NetEase API (when HTML page fails — e.g. /#/hash URLs)
        if (empty($result['title']) && $song_id) {
            $api = self::netease_api_get_song($song_id);
            if ($api) {
                $result = array_merge($result, $api);
            }
        }

        if (empty($result['tracks']) && !empty($result['title'])) {
            $result['tracks'][] = [
                'title' => $result['title'], 'artist' => $result['artist'],
                'duration' => '', 'url' => $url,
            ];
        }

        return $result;
    }

    /**
     * NetEase Cloud Music API — song detail.
     */
    private static function netease_api_get_song($id) {
        $resp = wp_remote_get('https://music.163.com/api/song/detail/?id=' . $id . '&ids=%5B' . $id . '%5D', [
            'timeout'    => 15,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers'    => [
                'Referer' => 'https://music.163.com/',
                'Accept'  => 'application/json',
            ],
        ]);

        if (is_wp_error($resp)) {
            self::$last_errors[] = 'NetEase API: ' . $resp->get_error_message();
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!$body || ($body['code'] ?? -1) !== 200) {
            self::$last_errors[] = 'NetEase API: invalid response, code=' . ($body['code'] ?? 'null');
            return null;
        }

        $songs = $body['songs'] ?? [];
        $song = $songs[0] ?? null;
        if (!$song) {
            self::$last_errors[] = 'NetEase API: no song data for id=' . $id;
            return null;
        }

        $artist_names = [];
        if (!empty($song['ar'])) {
            foreach ($song['ar'] as $ar) {
                $artist_names[] = $ar['name'] ?? '';
            }
        }

        $album = $song['al'] ?? [];
        $cover = $album['picUrl'] ?? '';
        if ($cover) {
            $cover = preg_replace('/\?param=\d+x\d+/', '?param=300x300', $cover);
        }

        // Try album tracks if this is from an album
        $tracks = [];
        $album_id = $album['id'] ?? 0;

        return [
            'title'       => $song['name'] ?? '',
            'artist'      => implode(' / ', array_filter($artist_names)),
            'album'       => $album['name'] ?? '',
            'coverUrl'    => $cover,
            'releaseDate' => self::extract_date($album['publishTime'] ?? ''),
            'genre'       => '',
            'detectedType' => 'single',
            '_album_id'   => $album_id,
        ];
    }

    // ═══════════════════════════════════════════════════
    // QQ Music — API-based (SPA page has no data)
    // ═══════════════════════════════════════════════════

    private static function fetch_qqmusic($url) {
        $result = [
            'title' => '', 'artist' => '', 'album' => '',
            'coverUrl' => '', 'releaseDate' => '', 'genre' => '',
            'tracks' => [], 'detectedType' => '',
        ];

        // Extract song mid from URL
        $mid = self::extract_qq_songmid($url);
        if (!$mid) return $result;

        // Try API: get song detail
        $api_data = self::qq_api_get_song($mid);
        if ($api_data) {
            $result['title']       = $api_data['title'] ?? '';
            $result['artist']      = $api_data['artist'] ?? '';
            $result['album']       = $api_data['album'] ?? '';
            $result['coverUrl']    = $api_data['coverUrl'] ?? '';
            $result['releaseDate'] = $api_data['releaseDate'] ?? '';
            $result['genre']       = $api_data['genre'] ?? '';

            if (!empty($api_data['album_mid'])) {
                // Try to get album tracks
                $tracks = self::qq_api_get_album_tracks($api_data['album_mid']);
                if ($tracks && count($tracks) > 1) {
                    $result['tracks'] = $tracks;
                    $result['detectedType'] = 'album';
                }
            }
        }

        // Fallback: try page HTML just in case
        if (empty($result['title'])) {
            $html = self::get_page($url);
            if ($html) {
                $meta = self::parse_meta($html);
                if (!empty($meta['og:title'])) $result['title'] = $meta['og:title'];
                if (!empty($meta['og:image'])) $result['coverUrl'] = $meta['og:image'];
            }
        }

        if (empty($result['tracks']) && !empty($result['title'])) {
            $result['tracks'][] = [
                'title' => $result['title'], 'artist' => $result['artist'],
                'duration' => '', 'url' => $url,
            ];
        }

        return $result;
    }

    /**
     * Extract song mid from QQ Music URL.
     * Supports: y.qq.com/n/ryqq/songDetail/XXXXX, y.qq.com/n/ryqq/song/XXXXX
     */
    private static function extract_qq_songmid($url) {
        // Pattern: /songDetail/XXXXX or /song/XXXXX
        if (preg_match('#/(?:songDetail|song)/([a-zA-Z0-9_-]+)#i', $url, $m)) {
            return $m[1];
        }
        // Query param: ?songmid=XXXXX or ?id=XXXXX
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            if (!empty($params['songmid'])) return $params['songmid'];
            if (!empty($params['id'])) return $params['id'];
        }
        return '';
    }

    /**
     * Call QQ Music API to get song details.
     */
    private static function qq_api_get_song($mid) {
        $resp = wp_remote_post('https://u.y.qq.com/cgi-bin/musicu.fcg', [
            'timeout'   => 15,
            'sslverify' => false,
            'headers' => [
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer'       => 'https://y.qq.com/',
                'Origin'        => 'https://y.qq.com',
            ],
            'body' => json_encode([
                'comm'     => ['ct' => 24, 'cv' => 0],
                'songinfo' => [
                    'method' => 'get_song_detail_yqq',
                    'param'  => ['song_mid' => $mid],
                    'module' => 'music.pf_song_detail_svr',
                ],
            ]),
        ]);

        if (is_wp_error($resp)) {
            self::$last_errors[] = 'QQ API song: ' . $resp->get_error_message();
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!$body || ($body['code'] ?? -1) !== 0) {
            self::$last_errors[] = 'QQ API song: invalid response, code=' . ($body['code'] ?? 'null');
            return null;
        }

        $info = $body['songinfo']['data']['track_info'] ?? null;
        if (!$info || (empty($info['name']) && empty($info['title']))) {
            self::$last_errors[] = 'QQ API song: empty track_info for mid=' . $mid;
            return null;
        }

        $singer = '';
        if (!empty($info['singer']) && is_array($info['singer'])) {
            $names = array_column($info['singer'], 'name');
            $singer = implode(' / ', array_filter($names));
        }

        $album_mid = $info['album']['mid'] ?? '';
        $cover_url = '';
        if (!empty($album_mid)) {
            $cover_url = "https://y.qq.com/music/photo_new/T002R300x300M000{$album_mid}.jpg";
        }

        $genre_map = [
            0 => '', 1 => '流行', 2 => '摇滚', 3 => '民谣', 4 => '电子',
            5 => '舞曲', 6 => '说唱', 7 => '轻音乐', 8 => '爵士',
            9 => '乡村', 10 => '古典', 11 => '民族', 12 => '蓝调',
            13 => '雷鬼', 14 => '世界音乐', 15 => '拉丁', 16 => '另类/独立',
            17 => 'New Age', 18 => 'R&B', 19 => '原声',
        ];
        $genre_id = $info['genre'] ?? 0;
        $genre = $genre_map[$genre_id] ?? '';

        return [
            'title'       => $info['name'] ?: $info['title'] ?: '',
            'artist'      => $singer,
            'album'       => $info['album']['name'] ?? '',
            'coverUrl'    => $cover_url,
            'releaseDate' => self::extract_date($info['time_public'] ?? ''),
            'genre'       => $genre,
            'album_mid'   => $album_mid,
        ];
    }

    /**
     * Get album tracks from QQ Music API.
     */
    private static function qq_api_get_album_tracks($album_mid) {
        $resp = wp_remote_post('https://u.y.qq.com/cgi-bin/musicu.fcg', [
            'timeout'   => 15,
            'sslverify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer'      => 'https://y.qq.com/',
            ],
            'body' => json_encode([
                'comm'    => ['ct' => 24, 'cv' => 0],
                'album'   => [
                    'method' => 'get_album_detail_yqq',
                    'param'  => ['album_mid' => $album_mid],
                    'module' => 'music.pf_album_detail_svr',
                ],
            ]),
        ]);

        if (is_wp_error($resp)) return null;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $tracks_data = $body['album']['data']['songlist'] ?? $body['album']['data']['list'] ?? null;
        if (!$tracks_data) return null;

        $tracks = [];
        foreach ($tracks_data as $track) {
            $singer_names = [];
            if (!empty($track['singer'])) {
                $singer_names = array_column($track['singer'], 'name');
            }
            $tracks[] = [
                'title'    => $track['songname'] ?? $track['name'] ?? '',
                'artist'   => implode(' / ', array_filter($singer_names)),
                'duration' => self::fmt_duration($track['interval'] ?? 0),
                'url'      => 'https://y.qq.com/n/ryqq/songDetail/' . ($track['songmid'] ?? $track['mid'] ?? ''),
            ];
        }

        return $tracks;
    }

    // ═══════════════════════════════════════════════════
    // Shared helpers
    // ═══════════════════════════════════════════════════

    private static function get_page($url) {
        $resp = wp_remote_get($url, [
            'timeout'    => 20,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers'    => [
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            ],
        ]);
        if (is_wp_error($resp)) {
            self::$last_errors[] = 'GET ' . parse_url($url, PHP_URL_HOST) . ': ' . $resp->get_error_message();
            return '';
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $len  = strlen($body);
        self::$last_errors[] = 'GET ' . parse_url($url, PHP_URL_HOST) . ': HTTP ' . $code . ', body ' . $len . ' bytes';
        return $body;
    }

    /**
     * Parse meta tags — handles both attribute orders.
     */
    private static function parse_meta($html) {
        $meta = [];

        // Pass 1: property/name before content
        if (preg_match_all(
            '/<meta\s+[^>]*?(?:property|name)\s*=\s*["\']([^"\']+)["\'][^>]*?content\s*=\s*["\']([^"\']*)["\']/is',
            $html, $m, PREG_SET_ORDER
        )) {
            foreach ($m as $match) {
                $key = strtolower($match[1]);
                if (!isset($meta[$key])) {
                    $meta[$key] = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
                }
            }
        }

        // Pass 2: content before property/name
        if (preg_match_all(
            '/<meta\s+[^>]*?content\s*=\s*["\']([^"\']*)["\'][^>]*?(?:property|name)\s*=\s*["\']([^"\']+)["\']/is',
            $html, $m, PREG_SET_ORDER
        )) {
            foreach ($m as $match) {
                $key = strtolower($match[2]);
                if (!isset($meta[$key])) {
                    $meta[$key] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
                }
            }
        }

        return $meta;
    }

    /**
     * Parse JSON-LD schema — finds music-related schemas.
     */
    private static function parse_jsonld($html) {
        if (!preg_match_all(
            '/<script[^>]*type\s*=\s*["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/s',
            $html, $scripts, PREG_SET_ORDER
        )) return null;

        foreach ($scripts as $s) {
            $json = json_decode($s[1], true);
            if (!$json) continue;
            $type = $json['@type'] ?? '';
            if (in_array($type, ['MusicComposition', 'MusicRecording', 'MusicAlbum', 'MusicPlaylist'])) {
                return $json;
            }
        }
        // Fallback: first JSON-LD with a name
        foreach ($scripts as $s) {
            $json = json_decode($s[1], true);
            if ($json && !empty($json['name'])) return $json;
        }
        return null;
    }

    /**
     * Extract song name from Apple Music og:title.
     * "Apple Music 上陶喆的歌曲《每一面都美》" → "每一面都美"
     */
    private static function parse_apple_og_title($og_title) {
        if (preg_match('/[《「](.+?)[》」]/u', $og_title, $m)) return $m[1];
        return $og_title;
    }

    /**
     * Extract date from ISO 8601 or similar formats.
     */
    private static function extract_date($str) {
        $str = trim($str);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $str, $m)) return $m[1];
        if (preg_match('/^(\d{4})/', $str, $m)) return $m[1];
        return $str;
    }

    private static function fmt_duration($seconds) {
        $s = intval($seconds);
        if ($s <= 0) return '';
        return sprintf('%d:%02d', floor($s / 60), $s % 60);
    }
}
