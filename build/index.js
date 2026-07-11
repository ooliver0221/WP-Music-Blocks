/**
 * WP-Music-Blocks - Block Editor Script
 * Horizontal card (single), vertical card (album), color extraction
 */
(function (wp) {
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useCallback = wp.element.useCallback;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var MediaPlaceholder = wp.blockEditor.MediaPlaceholder;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var SelectControl = wp.components.SelectControl;
    var Notice = wp.components.Notice;
    var apiFetch = wp.apiFetch;

    var PLATFORM_LABELS = {
        apple_music: 'Apple Music',
        netease: '网易云音乐',
        qqmusic: 'QQ音乐',
    };

    // =======================================================
    // MusicCard Preview Component
    // =======================================================
    function MusicCard(props) {
        var data = props.data;
        var colors = props.colors;
        var cardType = props.cardType;

        if (!data) return null;

        var title = data.title || '未知歌曲';
        var artist = data.artist || '';
        var album = data.album || '';
        var coverUrl = data.coverUrl || '';
        var releaseDate = data.releaseDate || '';
        var genre = data.genre || '';
        var platform = data.platform || '';
        var tracks = data.tracks || [];
        var url = data.url || '';
        var isAlbum = cardType === 'album';
        var showTracklist = isAlbum && tracks && tracks.length > 0;
        var platformLabel = PLATFORM_LABELS[platform] || '音乐平台';

        var cardStyle = colors ? {
            '--wpmb-accent': colors.accent,
            '--wpmb-main-color': colors.mainColor,
            '--wpmb-bg-image': colors.bgImage,
            '--wpmb-text-color': colors.textColor,
        } : {};

        // Shared cover element
        var coverEl = el('div', { className: 'wpmb-card-cover' },
            coverUrl
                ? el('img', { src: coverUrl, alt: title, className: 'wpmb-cover-img' })
                : el('div', { className: 'wpmb-cover-placeholder' },
                    el('svg', { viewBox: '0 0 24 24', width: 32, height: 32, fill: 'currentColor' },
                        el('path', { d: 'M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z' })
                    )
                )
        );

        // Shared track list
        var tracklistEl = showTracklist ? el('div', { className: 'wpmb-tracklist' },
            el('ol', { className: 'wpmb-tracks' },
                tracks.map(function (track, i) {
                    return el('li', { key: i, className: 'wpmb-track-item' },
                        el('span', { className: 'wpmb-track-num' }, i + 1),
                        el('span', { className: 'wpmb-track-info' },
                            el('span', { className: 'wpmb-track-title' }, track.title || ''),
                            track.artist ? el('span', { className: 'wpmb-track-artist' }, ' — ' + track.artist) : null
                        ),
                        track.duration ? el('span', { className: 'wpmb-track-time' }, track.duration) : null
                    );
                })
            )
        ) : null;

        // ── Album layout: cover + info side by side, tracklist below ──
        if (isAlbum) {
            return el('div', {
                className: 'wpmb-card wpmb-card--album wpmb-preview',
                style: cardStyle,
                'data-platform': platform,
                'data-type': cardType,
            },
                el('a', {
                    href: url || '#',
                    className: 'wpmb-card-inner wpmb-card-inner--album',
                    onClick: function (e) { e.preventDefault(); },
                },
                    el('div', { className: 'wpmb-card-main' },
                        coverEl,
                        el('div', { className: 'wpmb-card-body' },
                            el('h3', { className: 'wpmb-card-title' }, title),
                            artist ? el('p', { className: 'wpmb-card-artist' }, artist) : null,
                            el('div', { className: 'wpmb-card-meta' },
                                releaseDate ? el('span', { className: 'wpmb-meta-text' }, releaseDate) : null,
                                genre ? el('span', { className: 'wpmb-meta-text' }, genre) : null,
                                el('span', { className: 'wpmb-meta-platform' }, platformLabel)
                            )
                        )
                    ),
                    tracklistEl
                )
            );
        }

        // ── Single layout: horizontal (cover left, info right) ──
        return el('div', {
            className: 'wpmb-card wpmb-preview',
            style: cardStyle,
            'data-platform': platform,
            'data-type': cardType,
        },
            el('a', {
                href: url || '#',
                className: 'wpmb-card-inner',
                onClick: function (e) { e.preventDefault(); },
            },
                coverEl,
                el('div', { className: 'wpmb-card-body' },
                    el('h3', { className: 'wpmb-card-title' }, title),
                    artist ? el('p', { className: 'wpmb-card-artist' }, artist) : null,
                    album ? el('p', { className: 'wpmb-card-album' }, album) : null,
                    el('div', { className: 'wpmb-card-meta' },
                        releaseDate ? el('span', { className: 'wpmb-meta-text' }, releaseDate) : null,
                        genre ? el('span', { className: 'wpmb-meta-text' }, genre) : null,
                        el('span', { className: 'wpmb-meta-platform' }, platformLabel)
                    )
                )
            )
        );
    }

    // =======================================================
    // Edit Component
    // =======================================================
    function Edit(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;

        var url = attributes.url || '';
        var cardType = attributes.cardType || 'single';
        var musicData = attributes.musicData;
        var colors = attributes.colors;
        var selectedPlatform = attributes.selectedPlatform || '';

        var _s1 = useState(false);
        var isLoading = _s1[0]; var setIsLoading = _s1[1];
        var _s2 = useState('');
        var error = _s2[0]; var setError = _s2[1];
        var _s3 = useState(0);
        var fetchTrigger = _s3[0]; var setFetchTrigger = _s3[1];

        var blockProps = useBlockProps({ className: 'wpmb-block-editor' });

        // Fetch music data
        var fetchMusicData = useCallback(function (musicUrl, platform) {
            if (!musicUrl || musicUrl.trim() === '') { setError(''); return; }
            setIsLoading(true);
            setError('');
            apiFetch({
                path: '/music-blocks/v1/fetch',
                method: 'POST',
                data: { url: musicUrl, platform: platform || '' },
            }).then(function (res) {
                if (res.success) {
                    var dt = res.data;
                    if (!dt.platform || dt.platform === 'unknown') {
                        dt.platform = platform || '';
                    }
                    setAttributes({ musicData: dt, colors: res.colors });

                    var detected = (dt.type === 'album' || (dt.tracks && dt.tracks.length > 1)) ? 'album' : 'single';
                    setAttributes({ cardType: detected });

                    if (res.diagnostic) {
                        console.log('WP-Music-Blocks diagnostic:', JSON.stringify(res.diagnostic));
                    }
                    if (!dt.title && res.diagnostic && res.diagnostic.hint) {
                        setError(res.diagnostic.hint);
                    } else if (!dt.title) {
                        setError('未能获取到歌曲信息。请确认链接有效，或手动选择正确的平台后重试。');
                    }
                } else {
                    var errInfo = res.error;
                    if (errInfo) {
                        setError('PHP 错误: ' + errInfo.message + ' [' + errInfo.file + ':' + errInfo.line + ']');
                    } else {
                        setError('获取音乐信息失败');
                    }
                }
            }).catch(function (err) {
                setError('请求失败，HTTP ' + (err.status || '') + ': ' + (err.message || '未知网络错误'));
            }).finally(function () { setIsLoading(false); });
        }, [setAttributes]);

        // Auto-fetch on URL change
        useEffect(function () {
            if (!url || url.trim() === '') return;
            var timer = setTimeout(function () {
                fetchMusicData(url, selectedPlatform);
            }, 600);
            return function () { clearTimeout(timer); };
        }, [url, fetchTrigger, selectedPlatform]);

        function handleRefresh() { setFetchTrigger(function (p) { return p + 1; }); }
        function handleUrlChange(v) { setAttributes({ url: v }); }
        function handlePlatformChange(v) { setAttributes({ selectedPlatform: v }); fetchMusicData(url, v); }
        function handleCardTypeChange(type) { setAttributes({ cardType: type }); }
        function handleCoverChange(media) {
            if (media && media.url) {
                var updated = Object.assign({}, musicData || {}, { coverUrl: media.url });
                setAttributes({ musicData: updated });
            }
        }

        // ── No URL yet: show placeholder ──
        if (!url || url.trim() === '') {
            return el('div', blockProps,
                el('div', { className: 'wpmb-editor-placeholder' },
                    el('div', { className: 'wpmb-placeholder-icon' },
                        el('svg', { viewBox: '0 0 24 24', width: 40, height: 40, fill: 'currentColor' },
                            el('path', { d: 'M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z' })
                        )
                    ),
                    el('h3', { className: 'wpmb-placeholder-title' }, '音乐卡片'),
                    el('p', { className: 'wpmb-placeholder-desc' },
                        '粘贴 Apple Music、QQ音乐 或 网易云音乐 的链接，自动生成音乐卡片。'
                    ),
                    el(TextControl, {
                        value: url,
                        onChange: handleUrlChange,
                        placeholder: 'https://music.apple.com/... 或 https://y.qq.com/...',
                        className: 'wpmb-url-input',
                        autoComplete: 'off',
                    }),
                    el('div', { style: { marginTop: '12px', display: 'flex', justifyContent: 'center' } },
                        el(SelectControl, {
                            value: selectedPlatform,
                            onChange: handlePlatformChange,
                            options: [
                                { label: '自动识别平台', value: '' },
                                { label: 'Apple Music', value: 'apple_music' },
                                { label: 'QQ音乐', value: 'qqmusic' },
                                { label: '网易云音乐', value: 'netease' },
                            ],
                            style: { maxWidth: '200px' },
                        })
                    )
                )
            );
        }

        // ── Inspector ──
        var inspector = el(InspectorControls, null,
            el(PanelBody, { title: '音乐链接', initialOpen: true },
                el(TextControl, {
                    label: '链接地址',
                    value: url,
                    onChange: handleUrlChange,
                    placeholder: 'https://...',
                    autoComplete: 'off',
                }),
                el(SelectControl, {
                    label: '音乐平台',
                    value: selectedPlatform || (musicData && musicData.platform) || '',
                    onChange: handlePlatformChange,
                    options: [
                        { label: '自动识别', value: '' },
                        { label: 'Apple Music', value: 'apple_music' },
                        { label: 'QQ音乐', value: 'qqmusic' },
                        { label: '网易云音乐', value: 'netease' },
                    ],
                    help: '如果自动识别不准确，请手动选择平台。',
                }),
                el(Button, { variant: 'secondary', onClick: handleRefresh, style: { width: '100%', marginTop: '8px' } }, '重新获取')
            ),
            el(PanelBody, { title: '卡片类型', initialOpen: true },
                el(SelectControl, {
                    label: '显示方式',
                    value: cardType,
                    onChange: handleCardTypeChange,
                    options: [
                        { label: '歌曲（单首）', value: 'single' },
                        { label: '专辑（含曲目列表）', value: 'album' },
                    ],
                })
            ),
            el(PanelBody, { title: '封面图片', initialOpen: false },
                el(MediaPlaceholder, {
                    labels: { title: '封面图片', instructions: '上传或选择一张封面图片。' },
                    icon: 'format-image',
                    onSelect: handleCoverChange,
                    accept: 'image/*',
                    allowedTypes: ['image'],
                    disableMediaButtons: false,
                })
            )
        );

        // ── Main content ──
        return el(wp.element.Fragment, null, inspector,
            el('div', blockProps,
                // Toolbar
                el('div', { className: 'wpmb-editor-toolbar' },
                    el(TextControl, {
                        value: url,
                        onChange: handleUrlChange,
                        placeholder: '粘贴音乐链接...',
                        className: 'wpmb-url-input-inline',
                        autoComplete: 'off',
                    }),
                    el(SelectControl, {
                        value: selectedPlatform || (musicData && musicData.platform) || '',
                        onChange: handlePlatformChange,
                        options: [
                            { label: '自动识别', value: '' },
                            { label: 'Apple Music', value: 'apple_music' },
                            { label: 'QQ音乐', value: 'qqmusic' },
                            { label: '网易云音乐', value: 'netease' },
                        ],
                        style: { width: '140px' },
                    }),
                    isLoading ? el(Spinner) : null,
                    el(Button, { variant: 'secondary', size: 'small', onClick: handleRefresh, icon: 'update', label: '刷新' })
                ),

                error ? el(Notice, { status: 'error', isDismissible: true, onDismiss: function () { setError(''); } }, error) : null,

                isLoading && !musicData
                    ? el('div', { className: 'wpmb-editor-loading' }, el(Spinner), el('p', null, '正在获取音乐信息...'))
                    : null,

                musicData ? el(MusicCard, {
                    data: musicData,
                    colors: colors,
                    cardType: cardType,
                }) : null
            )
        );
    }

    // =======================================================
    // Register
    // =======================================================
    registerBlockType('wp-music-blocks/music-card', {
        edit: Edit,
        save: function () { return null; },
    });

})(window.wp);
