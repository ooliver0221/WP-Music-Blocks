<?php
/**
 * Plugin Name: WP-Music-Blocks
 * Plugin URI: https://github.com/ooliver0221/wp-music-blocks
 * Description: 一个 Gutenberg 音乐卡片区块，支持 Apple Music、QQ音乐、网易云音乐。粘贴链接即可自动获取专辑封面、歌曲信息和曲目列表。
 * Version: 1.0.0
 * Author: ooliver
 * License: MIT
 * Text Domain: wp-music-blocks
 *
 * @package WPMusicBlocks
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin metadata from JSON
$wpmb_meta = json_decode(file_get_contents(__DIR__ . '/plugin-meta.json'), true);
define('WPMB_VERSION', $wpmb_meta['version'] ?? '1.0.0');
define('WPMB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPMB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load classes
require_once WPMB_PLUGIN_DIR . 'includes/class-music-api.php';
require_once WPMB_PLUGIN_DIR . 'includes/class-color-extractor.php';
require_once WPMB_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once WPMB_PLUGIN_DIR . 'includes/class-renderer.php';

// Initialize REST API
\WPMusicBlocks\REST_API::init();

/**
 * Register the music block.
 */
function wpmb_register_block() {
    wp_register_script(
        'wpmb-block-editor',
        WPMB_PLUGIN_URL . 'build/index.js',
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-i18n', 'wp-block-editor', 'wp-api-fetch'],
        WPMB_VERSION,
        true
    );

    wp_register_style(
        'wpmb-block-style',
        WPMB_PLUGIN_URL . 'build/style-index.css',
        [],
        WPMB_VERSION
    );

    wp_register_style(
        'wpmb-block-editor-style',
        WPMB_PLUGIN_URL . 'build/index.css',
        ['wp-edit-blocks'],
        WPMB_VERSION
    );

    register_block_type(WPMB_PLUGIN_DIR . 'block.json', [
        'render_callback' => ['\\WPMusicBlocks\\Renderer', 'render'],
    ]);
}
add_action('init', 'wpmb_register_block');

/**
 * Register plugin settings.
 */
function wpmb_register_settings() {
    register_setting('wpmb_settings', 'wpmb_language', [
        'type'              => 'string',
        'default'           => 'zh',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    add_settings_section('wpmb_main_section', '基本设置', null, 'wpmb_settings');

    add_settings_field(
        'wpmb_language_field',
        '界面语言',
        function () {
            $lang = get_option('wpmb_language', 'zh');
            ?>
            <select name="wpmb_language" id="wpmb_language">
                <option value="zh" <?php selected($lang, 'zh'); ?>>中文</option>
                <option value="en" <?php selected($lang, 'en'); ?>>English</option>
            </select>
            <p class="description">设置音乐卡片在编辑器中的显示语言。</p>
            <?php
        },
        'wpmb_settings',
        'wpmb_main_section'
    );

    register_setting('wpmb_settings', 'wpmb_font_family', [
        'type'              => 'string',
        'default'           => 'inherit',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    add_settings_field(
        'wpmb_font_field',
        '卡片字体',
        function () {
            $font = get_option('wpmb_font_family', 'inherit');
            ?>
            <input type="text" name="wpmb_font_family" id="wpmb_font_family"
                   value="<?php echo esc_attr($font); ?>" class="regular-text"
                   placeholder="inherit" />
            <p class="description">卡片使用的字体。默认 <code>inherit</code> 跟随主题字体。可填写任意 CSS font-family 值，例如 <code>"PingFang SC", "Microsoft YaHei", sans-serif</code>。</p>
            <?php
        },
        'wpmb_settings',
        'wpmb_main_section'
    );
}
add_action('admin_init', 'wpmb_register_settings');

/**
 * Add settings page to admin menu.
 */
function wpmb_add_settings_page() {
    add_options_page(
        'WP-Music-Blocks 设置',
        'Music Blocks',
        'manage_options',
        'wpmb_settings',
        'wpmb_render_settings_page'
    );
}
add_action('admin_menu', 'wpmb_add_settings_page');

/**
 * Render the settings page.
 */
function wpmb_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>WP-Music-Blocks 设置</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpmb_settings');
            do_settings_sections('wpmb_settings');
            submit_button('保存设置');
            ?>
        </form>
        <hr style="margin-top: 24px;" />
        <h2>使用说明</h2>
        <p>在文章编辑器中插入 <strong>音乐卡片</strong> 区块，粘贴以下平台的音乐链接即可自动生成卡片：</p>
        <ul style="list-style: disc; padding-left: 20px;">
            <li><strong>Apple Music</strong> — 如 <code>https://music.apple.com/cn/album/...</code></li>
            <li><strong>QQ音乐</strong> — 如 <code>https://y.qq.com/n/ryqq/songDetail/...</code></li>
            <li><strong>网易云音乐</strong> — 如 <code>https://music.163.com/song?id=...</code></li>
        </ul>
        <p>如果自动识别不准确，可以在区块右侧面板或顶部工具栏中手动选择平台。</p>
        <p>支持两种卡片类型：<strong>歌曲</strong> 和 <strong>专辑</strong>（含曲目列表）。</p>
    </div>
    <?php
}
