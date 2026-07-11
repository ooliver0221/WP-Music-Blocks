/**
 * WP-Music-Blocks - Music Card Preview Component
 * Single: horizontal (left cover, right info)
 * Album: vertical (cover top, info + tracklist below)
 */

const platformLabels = {
    apple_music: 'Apple Music',
    netease: '网易云音乐',
    qqmusic: 'QQ音乐',
};

export default function MusicCard({ data, colors, cardType, isPreview }) {
    if (!data) return null;

    const {
        title = '未知歌曲',
        artist = '',
        album = '',
        coverUrl = '',
        releaseDate = '',
        genre = '',
        platform = '',
        tracks = [],
        url = '',
    } = data;

    const isAlbum = cardType === 'album';
    const showTracklist = isAlbum && tracks && tracks.length > 0;
    const platformLabel = platformLabels[platform] || '音乐平台';

    const cardStyle = colors ? {
        '--wpmb-accent': colors.accent,
        '--wpmb-main-color': colors.mainColor,
        '--wpmb-bg-image': colors.bgImage,
        '--wpmb-text-color': colors.textColor,
    } : {};

    // Shared cover element
    const coverEl = (
        <div className="wpmb-card-cover">
            {coverUrl ? (
                <img src={coverUrl} alt={title} className="wpmb-cover-img" />
            ) : (
                <div className="wpmb-cover-placeholder" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
                        <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" />
                    </svg>
                </div>
            )}
        </div>
    );

    // Shared track list
    const tracklistEl = showTracklist ? (
        <div className="wpmb-tracklist">
            <ol className="wpmb-tracks">
                {tracks.map((track, i) => (
                    <li key={i} className="wpmb-track-item">
                        <span className="wpmb-track-num">{i + 1}</span>
                        <span className="wpmb-track-info">
                            <span className="wpmb-track-title">{track.title || ''}</span>
                            {track.artist && (
                                <span className="wpmb-track-artist"> — {track.artist}</span>
                            )}
                        </span>
                        {track.duration && (
                            <span className="wpmb-track-time">{track.duration}</span>
                        )}
                    </li>
                ))}
            </ol>
        </div>
    ) : null;

    // ── Album layout: cover + info side by side, tracklist below ──
    if (isAlbum) {
        return (
            <div
                className={`wpmb-card wpmb-card--album${isPreview ? ' wpmb-preview' : ''}`}
                style={cardStyle}
                data-platform={platform}
                data-type={cardType}
            >
                <a
                    href={url || '#'}
                    className="wpmb-card-inner wpmb-card-inner--album"
                    onClick={isPreview ? (e) => e.preventDefault() : undefined}
                >
                    <div className="wpmb-card-main">
                        {coverEl}
                        <div className="wpmb-card-body">
                            <h3 className="wpmb-card-title">{title}</h3>
                            {artist && <p className="wpmb-card-artist">{artist}</p>}
                            <div className="wpmb-card-meta">
                                {releaseDate && <span className="wpmb-meta-text">{releaseDate}</span>}
                                {genre && <span className="wpmb-meta-text">{genre}</span>}
                                <span className="wpmb-meta-platform">{platformLabel}</span>
                            </div>
                        </div>
                    </div>
                    {tracklistEl}
                </a>
            </div>
        );
    }

    // ── Single layout: horizontal ──
    return (
        <div
            className={`wpmb-card${isPreview ? ' wpmb-preview' : ''}`}
            style={cardStyle}
            data-platform={platform}
            data-type={cardType}
        >
            <a
                href={url || '#'}
                className="wpmb-card-inner"
                onClick={isPreview ? (e) => e.preventDefault() : undefined}
            >
                {coverEl}
                <div className="wpmb-card-body">
                    <h3 className="wpmb-card-title">{title}</h3>
                    {artist && <p className="wpmb-card-artist">{artist}</p>}
                    {album && <p className="wpmb-card-album">{album}</p>}
                    <div className="wpmb-card-meta">
                        {releaseDate && <span className="wpmb-meta-text">{releaseDate}</span>}
                        {genre && <span className="wpmb-meta-text">{genre}</span>}
                        <span className="wpmb-meta-platform">{platformLabel}</span>
                    </div>
                </div>
            </a>
        </div>
    );
}
