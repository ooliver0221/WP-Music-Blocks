<?php
/**
 * Color Extractor - Extract accent colors from album artwork for white-bg card design.
 *
 * @package WPMusicBlocks
 */

namespace WPMusicBlocks;

class Color_Extractor {

    private static $last_error = '';

    public static function get_last_error() {
        return self::$last_error;
    }

    public static function extract($image_url) {
        self::$last_error = '';
        if (empty($image_url)) {
            self::$last_error = 'empty image URL';
            return self::default_colors();
        }

        $image_data = self::download_image($image_url);
        if (!$image_data) {
            self::$last_error = 'failed to download image: ' . $image_url;
            return self::default_colors();
        }

        $image = @imagecreatefromstring($image_data);
        if (!$image) {
            self::$last_error = 'imagecreatefromstring failed, data length: ' . strlen($image_data);
            return self::default_colors();
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        // Resize small for fast processing
        $max_size  = 100;
        $scale     = min($max_size / $width, $max_size / $height, 1);
        $new_width  = max(1, intval($width * $scale));
        $new_height = max(1, intval($height * $scale));

        $resized = imagecreatetruecolor($new_width, $new_height);
        if (!$resized) {
            self::$last_error = 'imagecreatetruecolor failed';
            imagedestroy($image);
            return self::default_colors();
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);

        // Build color histogram, skip extreme values
        $colors = [];
        for ($x = 0; $x < $new_width; $x++) {
            for ($y = 0; $y < $new_height; $y++) {
                $rgb = imagecolorat($resized, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                if ($luminance < 20 || $luminance > 240) continue;

                $max_c = max($r, $g, $b);
                $min_c = min($r, $g, $b);
                $saturation = $max_c > 0 ? ($max_c - $min_c) / $max_c : 0;
                $weight = 1 + $saturation * 3;

                $qr = round($r / 16) * 16;
                $qg = round($g / 16) * 16;
                $qb = round($b / 16) * 16;

                $key = sprintf('%03d,%03d,%03d', $qr, $qg, $qb);
                if (!isset($colors[$key])) {
                    $colors[$key] = ['r' => 0, 'g' => 0, 'b' => 0, 'count' => 0, 'sat' => 0];
                }
                $colors[$key]['r'] += $r * $weight;
                $colors[$key]['g'] += $g * $weight;
                $colors[$key]['b'] += $b * $weight;
                $colors[$key]['count'] += $weight;
                $colors[$key]['sat'] += $saturation;
            }
        }
        imagedestroy($resized);

        if (empty($colors)) {
            self::$last_error = 'no usable colors found (all too dark/light)';
            return self::default_colors();
        }

        // Sort by pure frequency — most common color first
        uasort($colors, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // Pick top distinct colors (up to 5, lower threshold for more variety)
        $clusters = [];
        foreach ($colors as $key => $cluster) {
            $avg_r = round($cluster['r'] / $cluster['count']);
            $avg_g = round($cluster['g'] / $cluster['count']);
            $avg_b = round($cluster['b'] / $cluster['count']);

            $is_duplicate = false;
            foreach ($clusters as $existing) {
                $dist = sqrt(
                    pow($avg_r - $existing['r'], 2) +
                    pow($avg_g - $existing['g'], 2) +
                    pow($avg_b - $existing['b'], 2)
                );
                if ($dist < 35) { // Lower threshold = more variety
                    $is_duplicate = true;
                    break;
                }
            }

            if (!$is_duplicate) {
                $clusters[] = ['r' => $avg_r, 'g' => $avg_g, 'b' => $avg_b];
                if (count($clusters) >= 5) break;
            }
        }

        $main = $clusters[0];
        $main_color = sprintf('#%02x%02x%02x', $main['r'], $main['g'], $main['b']);

        // Build fluid blobs — deterministic harmonic variants from existing colors
        // Rotate through existing colors with fixed hue shifts (no randomness)
        $all_colors = $clusters;
        $shifts = [[40,5,-30], [-30,35,15], [10,-25,40], [-20,-10,35]];
        $si = 0;
        while (count($all_colors) < 5) {
            $base = $all_colors[$si % count($clusters)];
            $shift = $shifts[$si % count($shifts)];
            $all_colors[] = [
                'r' => max(0, min(255, $base['r'] + $shift[0])),
                'g' => max(0, min(255, $base['g'] + $shift[1])),
                'b' => max(0, min(255, $base['b'] + $shift[2])),
            ];
            $si++;
        }

        // Apple Music-style: very large, soft, low-opacity blobs that melt into each other
        // The key is large ellipses with low opacity — you only see soft edges blending
        $blob_configs = [
            ['x' => '5%',  'y' => '8%',  'w' => '95%', 'h' => '85%', 'op' => 0.28],
            ['x' => '75%', 'y' => '25%', 'w' => '85%', 'h' => '80%', 'op' => 0.22],
            ['x' => '35%', 'y' => '65%', 'w' => '90%', 'h' => '75%', 'op' => 0.24],
            ['x' => '70%', 'y' => '75%', 'w' => '80%', 'h' => '70%', 'op' => 0.18],
            ['x' => '50%', 'y' => '50%', 'w' => '75%', 'h' => '65%', 'op' => 0.14],
        ];

        $blobs = [];
        for ($i = 0; $i < 5; $i++) {
            $c = $all_colors[$i];
            $cfg = $blob_configs[$i];
            // Adaptive warmth shift for color harmony
            $shift = ($i === 0) ? 1.08 : (($i % 2 === 0) ? 1.04 : 0.94);
            $br = max(0, min(255, round($c['r'] * $shift)));
            $bg = max(0, min(255, round($c['g'] * $shift)));
            $bb = max(0, min(255, round($c['b'] * $shift)));
            // Softer edge: transparent at 70% instead of 60%
            $blobs[] = sprintf(
                'radial-gradient(ellipse %s %s at %s %s, rgba(%d,%d,%d,%.2f) 0%%, transparent 70%%)',
                $cfg['w'], $cfg['h'], $cfg['x'], $cfg['y'], $br, $bg, $bb, $cfg['op']
            );
        }
        $fluid_blobs = implode(", ", $blobs);

        // Frost overlay — directional ambient light: bright at top-left, shadow at bottom-right
        $frost = 'linear-gradient(140deg, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.03) 25%, transparent 50%, rgba(0,0,0,0.05) 75%, rgba(0,0,0,0.12) 100%)';

        $bg_css = $fluid_blobs . ", " . $frost;

        // Use the most frequent color directly as the background base
        $bg_color = sprintf('#%02x%02x%02x', $main['r'], $main['g'], $main['b']);

        $main_lum = 0.299 * $main['r'] + 0.587 * $main['g'] + 0.114 * $main['b'];
        $text_color = $main_lum < 145 ? '#ffffff' : '#1f2937';
        $hex = $main_color;

        self::$last_error = 'OK';

        return [
            'accent'     => $hex,
            'accentBg'   => sprintf('rgba(%d,%d,%d,0.15)', $main['r'], $main['g'], $main['b']),
            'trackHover' => sprintf('rgba(%d,%d,%d,0.10)', $main['r'], $main['g'], $main['b']),
            'mainColor'  => $bg_color,  // darker base for depth
            'radialGlow' => $fluid_blobs,
            'bgImage'    => $bg_css,
            'textColor'  => $text_color,
        ];
    }

    private static function download_image($url) {
        $response = wp_remote_get($url, [
            'timeout'    => 20,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (compatible; WP-Music-Blocks)',
        ]);
        if (is_wp_error($response)) {
            self::$last_error = 'HTTP error: ' . $response->get_error_message();
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            self::$last_error = 'HTTP ' . $code . ' for image URL';
            return false;
        }
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            self::$last_error = 'empty response body';
            return false;
        }
        return $body;
    }

    private static function default_colors() {
        $blobs = implode(", ", [
            'radial-gradient(ellipse 95% 85% at 5% 8%, rgba(139,92,246,0.28) 0%, transparent 70%)',
            'radial-gradient(ellipse 85% 80% at 75% 25%, rgba(99,102,241,0.22) 0%, transparent 70%)',
            'radial-gradient(ellipse 90% 75% at 35% 65%, rgba(167,139,250,0.24) 0%, transparent 70%)',
            'radial-gradient(ellipse 80% 70% at 70% 75%, rgba(236,72,153,0.18) 0%, transparent 70%)',
            'radial-gradient(ellipse 75% 65% at 50% 50%, rgba(192,132,252,0.14) 0%, transparent 70%)',
        ]);
        $frost = 'linear-gradient(140deg, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.03) 25%, transparent 50%, rgba(0,0,0,0.05) 75%, rgba(0,0,0,0.12) 100%)';
        return [
            'accent'     => '#6366f1',
            'accentBg'   => 'rgba(99,102,241,0.15)',
            'trackHover' => 'rgba(99,102,241,0.10)',
            'mainColor'  => '#2d1060',
            'radialGlow' => $blobs,
            'bgImage'    => $blobs . ", " . $frost,
            'textColor'  => '#ffffff',
        ];
    }
}
