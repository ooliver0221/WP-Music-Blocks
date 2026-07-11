/**
 * WP Music Blocks - Editor Component
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    PanelBody,
    TextControl,
    Button,
    Spinner,
    SelectControl,
    Notice,
} from '@wordpress/components';
import {
    InspectorControls,
    useBlockProps,
    MediaPlaceholder,
} from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import MusicCard from './components/MusicCard';

export default function Edit({ attributes, setAttributes }) {
    const {
        url,
        cardType,
        musicData,
        colors,
        selectedPlatform,
    } = attributes;

    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [fetchTrigger, setFetchTrigger] = useState(0);

    const blockProps = useBlockProps({
        className: 'wpmb-block-editor',
    });

    // Fetch music data
    const fetchMusicData = useCallback(async (musicUrl, platform) => {
        if (!musicUrl || musicUrl.trim() === '') {
            setError('');
            return;
        }

        setIsLoading(true);
        setError('');

        try {
            const res = await apiFetch({
                path: '/music-blocks/v1/fetch',
                method: 'POST',
                data: { url: musicUrl, platform: platform || '' },
            });

            if (res.success) {
                const dt = res.data;
                if (!dt.platform || dt.platform === 'unknown') {
                    dt.platform = platform || '';
                }
                setAttributes({ musicData: dt, colors: res.colors });

                const detected = (dt.type === 'album' || (dt.tracks && dt.tracks.length > 1))
                    ? 'album' : 'single';
                setAttributes({ cardType: detected });

                if (res.diagnostic) {
                    console.log('WP Music Blocks diagnostic:', JSON.stringify(res.diagnostic));
                }
                if (!dt.title && res.diagnostic && res.diagnostic.hint) {
                    setError(res.diagnostic.hint);
                } else if (!dt.title) {
                    setError('未能获取到歌曲信息。请确认链接有效，或手动选择正确的平台后重试。');
                }
            } else {
                const errInfo = res.error;
                if (errInfo) {
                    setError('PHP 错误: ' + errInfo.message + ' [' + errInfo.file + ':' + errInfo.line + ']');
                } else {
                    setError('获取音乐信息失败');
                }
            }
        } catch (err) {
            setError(
                '请求失败，HTTP ' + (err.status || '') + ': ' + (err.message || '未知网络错误')
            );
        } finally {
            setIsLoading(false);
        }
    }, [setAttributes]);

    // Auto-fetch on URL change
    useEffect(() => {
        if (!url || url.trim() === '') return;
        const timer = setTimeout(() => {
            fetchMusicData(url, selectedPlatform);
        }, 600);
        return () => clearTimeout(timer);
    }, [url, fetchTrigger, selectedPlatform]);

    const handleRefresh = () => setFetchTrigger((prev) => prev + 1);
    const handleUrlChange = (value) => setAttributes({ url: value });
    const handlePlatformChange = (value) => {
        setAttributes({ selectedPlatform: value });
        fetchMusicData(url, value);
    };
    const handleCardTypeChange = (type) => setAttributes({ cardType: type });
    const handleCoverChange = (media) => {
        if (media && media.url) {
            const updated = { ...(musicData || {}), coverUrl: media.url };
            setAttributes({ musicData: updated });
        }
    };

    // ── No URL yet: show placeholder ──
    if (!url || url.trim() === '') {
        return (
            <div {...blockProps}>
                <div className="wpmb-editor-placeholder">
                    <div className="wpmb-placeholder-icon">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor">
                            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" />
                        </svg>
                    </div>
                    <h3 className="wpmb-placeholder-title">音乐卡片</h3>
                    <p className="wpmb-placeholder-desc">
                        粘贴 Apple Music、QQ音乐 或 网易云音乐 的链接，自动生成音乐卡片。
                    </p>
                    <TextControl
                        value={url}
                        onChange={handleUrlChange}
                        placeholder="https://music.apple.com/... 或 https://y.qq.com/..."
                        className="wpmb-url-input"
                        autoComplete="off"
                    />
                    <div style={{ marginTop: '12px', display: 'flex', justifyContent: 'center' }}>
                        <SelectControl
                            value={selectedPlatform}
                            onChange={handlePlatformChange}
                            options={[
                                { label: '自动识别平台', value: '' },
                                { label: 'Apple Music', value: 'apple_music' },
                                { label: 'QQ音乐', value: 'qqmusic' },
                                { label: '网易云音乐', value: 'netease' },
                            ]}
                            style={{ maxWidth: '200px' }}
                        />
                    </div>
                </div>
            </div>
        );
    }

    // ── Inspector ──
    const inspector = (
        <InspectorControls>
            <PanelBody title="音乐链接" initialOpen={true}>
                <TextControl
                    label="链接地址"
                    value={url}
                    onChange={handleUrlChange}
                    placeholder="https://..."
                    autoComplete="off"
                />
                <SelectControl
                    label="音乐平台"
                    value={selectedPlatform || (musicData && musicData.platform) || ''}
                    onChange={handlePlatformChange}
                    options={[
                        { label: '自动识别', value: '' },
                        { label: 'Apple Music', value: 'apple_music' },
                        { label: 'QQ音乐', value: 'qqmusic' },
                        { label: '网易云音乐', value: 'netease' },
                    ]}
                    help="如果自动识别不准确，请手动选择平台。"
                />
                <Button
                    variant="secondary"
                    onClick={handleRefresh}
                    style={{ width: '100%', marginTop: '8px' }}
                >
                    重新获取
                </Button>
            </PanelBody>
            <PanelBody title="卡片类型" initialOpen={true}>
                <SelectControl
                    label="显示方式"
                    value={cardType}
                    onChange={handleCardTypeChange}
                    options={[
                        { label: '歌曲（单首）', value: 'single' },
                        { label: '专辑（含曲目列表）', value: 'album' },
                    ]}
                />
            </PanelBody>
            <PanelBody title="封面图片" initialOpen={false}>
                <MediaPlaceholder
                    labels={{ title: '封面图片', instructions: '上传或选择一张封面图片。' }}
                    icon="format-image"
                    onSelect={handleCoverChange}
                    accept="image/*"
                    allowedTypes={['image']}
                    disableMediaButtons={false}
                />
            </PanelBody>
        </InspectorControls>
    );

    // ── Main content ──
    return (
        <>
            {inspector}
            <div {...blockProps}>
                <div className="wpmb-editor-toolbar">
                    <TextControl
                        value={url}
                        onChange={handleUrlChange}
                        placeholder="粘贴音乐链接..."
                        className="wpmb-url-input-inline"
                        autoComplete="off"
                    />
                    <SelectControl
                        value={selectedPlatform || (musicData && musicData.platform) || ''}
                        onChange={handlePlatformChange}
                        options={[
                            { label: '自动识别', value: '' },
                            { label: 'Apple Music', value: 'apple_music' },
                            { label: 'QQ音乐', value: 'qqmusic' },
                            { label: '网易云音乐', value: 'netease' },
                        ]}
                        style={{ width: '140px' }}
                    />
                    {isLoading && <Spinner />}
                    <Button
                        variant="secondary"
                        size="small"
                        onClick={handleRefresh}
                        icon="update"
                        label="刷新"
                    />
                </div>

                {error && (
                    <Notice status="error" isDismissible onDismiss={() => setError('')}>
                        {error}
                    </Notice>
                )}

                {isLoading && !musicData && (
                    <div className="wpmb-editor-loading">
                        <Spinner />
                        <p>正在获取音乐信息...</p>
                    </div>
                )}

                {musicData && (
                    <MusicCard
                        data={musicData}
                        colors={colors}
                        cardType={cardType}
                    />
                )}
            </div>
        </>
    );
}
