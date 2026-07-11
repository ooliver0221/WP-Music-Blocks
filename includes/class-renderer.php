<?php
/**
 * Frontend renderer for the music block.
 *
 * @package WPMusicBlocks
 */

namespace WPMusicBlocks;

class Renderer {

    public static function render($attributes, $content, $block) {
        $music_data  = $attributes['musicData'] ?? null;
        $colors      = $attributes['colors'] ?? null;
        $card_type   = $attributes['cardType'] ?? 'single';
        $url         = $attributes['url'] ?? '';

        if (empty($music_data)) {
            return '';
        }

        $colors      = $colors ?: Color_Extractor::extract('');
        $title       = esc_html($music_data['title'] ?? '未知歌曲');
        $artist      = esc_html($music_data['artist'] ?? '');
        $album_name  = esc_html($music_data['album'] ?? '');
        $cover_url   = esc_url($music_data['coverUrl'] ?? '');
        $release_date = esc_html($music_data['releaseDate'] ?? '');
        $genre       = esc_html($music_data['genre'] ?? '');
        $platform    = esc_html($music_data['platform'] ?? '');
        $tracks      = $music_data['tracks'] ?? [];
        $redirect_url = esc_url($url ?: ($music_data['url'] ?? '#'));

        $accent      = esc_attr($colors['accent'] ?? '#6366f1');
        $main_color  = esc_attr($colors['mainColor'] ?? '#4c1d95');
        $bg_image    = esc_attr($colors['bgImage'] ?? '');
        $text_color  = esc_attr($colors['textColor'] ?? '#ffffff');
        $font_family = esc_attr(get_option('wpmb_font_family', 'inherit'));

        $platform_labels = [
            'apple_music' => 'Apple Music',
            'netease'     => '网易云音乐',
            'qqmusic'     => 'QQ音乐',
        ];
        $platform_label = $platform_labels[$platform] ?? '音乐平台';

        $is_album = $card_type === 'album';
        $show_tracklist = $is_album && !empty($tracks);

        $card_style = "--wpmb-accent: $accent; --wpmb-main-color: $main_color; --wpmb-bg-image: $bg_image; --wpmb-text-color: $text_color; --wpmb-font: $font_family;";

        ob_start();

        // Cover element
        $cover_html = '';
        if ($cover_url) {
            $cover_html = '<img src="' . $cover_url . '" alt="' . esc_attr($title) . '" loading="lazy" class="wpmb-cover-img" />';
        } else {
            $cover_html = '<div class="wpmb-cover-placeholder" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
                    <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                </svg>
            </div>';
        }

        // Track list HTML
        $tracklist_html = '';
        if ($show_tracklist) {
            $tracklist_html = '<div class="wpmb-tracklist"><ol class="wpmb-tracks">';
            foreach ($tracks as $index => $track) {
                $t_title = esc_html($track['title'] ?? '');
                $t_artist = esc_html($track['artist'] ?? '');
                $t_duration = esc_html($track['duration'] ?? '');
                $tracklist_html .= '<li class="wpmb-track-item">';
                $tracklist_html .= '<span class="wpmb-track-num">' . ($index + 1) . '</span>';
                $tracklist_html .= '<span class="wpmb-track-info"><span class="wpmb-track-title">' . $t_title . '</span>';
                if ($t_artist) {
                    $tracklist_html .= '<span class="wpmb-track-artist"> — ' . $t_artist . '</span>';
                }
                $tracklist_html .= '</span>';
                if ($t_duration) {
                    $tracklist_html .= '<span class="wpmb-track-time">' . $t_duration . '</span>';
                }
                $tracklist_html .= '</li>';
            }
            $tracklist_html .= '</ol></div>';
        }

        if ($is_album):
            // ── Album layout: cover + info side by side, tracklist below ──
        ?>
        <div class="wpmb-card wpmb-card--album"
             style="<?php echo $card_style; ?>"
             data-platform="<?php echo esc_attr($platform); ?>"
             data-type="album">
            <a href="<?php echo $redirect_url; ?>"
               target="_blank"
               rel="noopener noreferrer nofollow"
               class="wpmb-card-inner wpmb-card-inner--album"
               aria-label="<?php echo esc_attr(sprintf('在 %s 上收听 %s', $platform_label, $title)); ?>">

                <div class="wpmb-card-main">
                    <div class="wpmb-card-cover">
                        <?php echo $cover_html; ?>
                    </div>

                    <div class="wpmb-card-body">
                        <h3 class="wpmb-card-title"><?php echo $title; ?></h3>
                        <?php if ($artist): ?>
                            <p class="wpmb-card-artist"><?php echo $artist; ?></p>
                        <?php endif; ?>

                        <div class="wpmb-card-meta">
                            <?php if ($release_date): ?>
                                <span class="wpmb-meta-text"><?php echo $release_date; ?></span>
                            <?php endif; ?>
                            <?php if ($genre): ?>
                                <span class="wpmb-meta-text"><?php echo $genre; ?></span>
                            <?php endif; ?>
                            <span class="wpmb-meta-platform"><?php echo $platform_label; ?></span>
                        </div>
                    </div>
                </div>

                <?php echo $tracklist_html; ?>
            </a>
        </div>

        <?php else: // ── Single layout: horizontal (cover left, info right) ── ?>
        <div class="wpmb-card"
             style="<?php echo $card_style; ?>"
             data-platform="<?php echo esc_attr($platform); ?>"
             data-type="single">
            <a href="<?php echo $redirect_url; ?>"
               target="_blank"
               rel="noopener noreferrer nofollow"
               class="wpmb-card-inner"
               aria-label="<?php echo esc_attr(sprintf('在 %s 上收听 %s', $platform_label, $title)); ?>">

                <div class="wpmb-card-cover">
                    <?php echo $cover_html; ?>
                </div>

                <div class="wpmb-card-body">
                    <h3 class="wpmb-card-title"><?php echo $title; ?></h3>
                    <?php if ($artist): ?>
                        <p class="wpmb-card-artist"><?php echo $artist; ?></p>
                    <?php endif; ?>

                    <?php if ($album_name): ?>
                        <p class="wpmb-card-album"><?php echo $album_name; ?></p>
                    <?php endif; ?>

                    <div class="wpmb-card-meta">
                        <?php if ($release_date): ?>
                            <span class="wpmb-meta-text"><?php echo $release_date; ?></span>
                        <?php endif; ?>
                        <?php if ($genre): ?>
                            <span class="wpmb-meta-text"><?php echo $genre; ?></span>
                        <?php endif; ?>
                        <span class="wpmb-meta-platform"><?php echo $platform_label; ?></span>
                    </div>
                </div>
            </a>
        </div>
        <?php endif;

        return ob_get_clean();
    }
}
