<?php
/**
 * REST API endpoints for WP-Music-Blocks.
 *
 * @package WPMusicBlocks
 */

namespace WPMusicBlocks;

class REST_API {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('music-blocks/v1', '/fetch', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_fetch'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'url' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'platform' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Diagnostic: test HTTP connectivity
        register_rest_route('music-blocks/v1', '/test', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'handle_test'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    /**
     * Handle music metadata fetch.
     */
    public static function handle_fetch($request) {
        try {
            $url      = $request->get_param('url');
            $platform = $request->get_param('platform') ?: '';

            if (empty($url)) {
                return new \WP_Error('no_url', '请提供音乐链接。', ['status' => 400]);
            }

            $data   = Music_API::fetch($url, $platform);
            $errors = Music_API::get_last_errors();

            if (!empty($data['coverUrl'])) {
                $colors = Color_Extractor::extract($data['coverUrl']);
            } else {
                $colors = Color_Extractor::extract('');
            }

            $diagnostic = [];
            if (empty($data['title'])) {
                $is_library = strpos($url, '/library/') !== false;
                $diagnostic = [
                    'platform_detected' => $data['platform'] ?? 'unknown',
                    'fetch_errors'      => $errors,
                    'hint'              => $is_library
                        ? 'Apple Music 个人资料库链接需要登录才能访问，服务器无法获取。请使用公开的歌曲/专辑链接（例如 music.apple.com/cn/album/...）。'
                        : '服务器无法访问音乐平台页面。请确认链接是否正确，或手动选择平台后重试。',
                ];
            }

            $diagnostic['color_extract_status'] = Color_Extractor::get_last_error();
            $diagnostic['cover_url_used'] = $data['coverUrl'] ?? '(none)';

            return rest_ensure_response([
                'success'    => true,
                'data'       => $data,
                'colors'     => $colors,
                'diagnostic' => $diagnostic,
            ]);
        } catch (\Throwable $e) {
            return rest_ensure_response([
                'success' => false,
                'data'    => null,
                'colors'  => Color_Extractor::extract(''),
                'error'   => [
                    'message' => $e->getMessage(),
                    'file'    => str_replace(ABSPATH, '', $e->getFile()),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ],
            ]);
        }
    }

    /**
     * Diagnostic endpoint — test connectivity to each music platform.
     */
    public static function handle_test() {
        $urls = [
            'apple_music' => 'https://music.apple.com/cn/song/每一面都美/905198185',
            'netease'     => 'https://music.163.com/song?id=186116',
            'qqmusic'     => 'https://u.y.qq.com/cgi-bin/musicu.fcg',
        ];

        $results = [];
        foreach ($urls as $name => $url) {
            $start  = microtime(true);
            $method = $name === 'qqmusic' ? 'POST' : 'GET';
            $args = [
                'timeout'   => 15,
                'sslverify' => false,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'headers'   => ['Accept-Language' => 'zh-CN,zh;q=0.9'],
            ];
            if ($method === 'POST') {
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = json_encode([
                    'comm'     => ['ct' => 24, 'cv' => 0],
                    'songinfo' => [
                        'method' => 'get_song_detail_yqq',
                        'param'  => ['song_mid' => '002NkGGD3wWXYZ'],
                        'module' => 'music.pf_song_detail_svr',
                    ],
                ]);
            }
            $resp = $method === 'POST' ? wp_remote_post($url, $args) : wp_remote_get($url, $args);
            $elapsed = round((microtime(true) - $start) * 1000);

            $results[$name] = [
                'reachable'  => !is_wp_error($resp),
                'http_code'  => is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp),
                'elapsed_ms' => $elapsed,
                'error'      => is_wp_error($resp) ? $resp->get_error_message() : '',
            ];
        }

        return rest_ensure_response([
            'php_version'    => PHP_VERSION,
            'curl_enabled'   => function_exists('curl_version'),
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'openssl_loaded' => extension_loaded('openssl'),
            'results'        => $results,
        ]);
    }
}
