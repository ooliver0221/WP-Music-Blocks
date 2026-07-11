/**
 * WP Music Blocks - Custom Playlist Editor Component
 */
import { __ } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';

export default function PlaylistEditor({ tracks = [], onChange }) {
    const addTrack = () => {
        const newTracks = [
            ...tracks,
            { title: '', artist: '', duration: '', url: '', coverUrl: '' },
        ];
        onChange(newTracks);
    };

    const removeTrack = (index) => {
        const newTracks = tracks.filter((_, i) => i !== index);
        onChange(newTracks);
    };

    const updateTrack = (index, field, value) => {
        const newTracks = tracks.map((track, i) => {
            if (i === index) {
                return { ...track, [field]: value };
            }
            return track;
        });
        onChange(newTracks);
    };

    const moveTrack = (index, direction) => {
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= tracks.length) return;
        const newTracks = [...tracks];
        [newTracks[index], newTracks[newIndex]] = [newTracks[newIndex], newTracks[index]];
        onChange(newTracks);
    };

    return (
        <div className="wpmb-playlist-editor">
            <h4 className="wpmb-editor-title">
                {__('Custom Playlist', 'wp-music-blocks')}
            </h4>
            <p className="wpmb-editor-desc">
                {__('Add tracks to your custom playlist.', 'wp-music-blocks')}
            </p>

            {tracks.length === 0 && (
                <p className="wpmb-editor-empty">
                    {__('No tracks yet. Click the button below to add one.', 'wp-music-blocks')}
                </p>
            )}

            <div className="wpmb-tracks-editor-list">
                {tracks.map((track, index) => (
                    <div key={index} className="wpmb-track-editor-item">
                        <div className="wpmb-track-editor-header">
                            <span className="wpmb-track-editor-index">
                                {index + 1}
                            </span>
                            <div className="wpmb-track-editor-actions">
                                <Button
                                    variant="tertiary"
                                    size="small"
                                    icon="arrow-up-alt2"
                                    onClick={() => moveTrack(index, -1)}
                                    disabled={index === 0}
                                    label={__('Move up', 'wp-music-blocks')}
                                />
                                <Button
                                    variant="tertiary"
                                    size="small"
                                    icon="arrow-down-alt2"
                                    onClick={() => moveTrack(index, 1)}
                                    disabled={index === tracks.length - 1}
                                    label={__('Move down', 'wp-music-blocks')}
                                />
                                <Button
                                    variant="tertiary"
                                    size="small"
                                    isDestructive
                                    icon="trash"
                                    onClick={() => removeTrack(index)}
                                    label={__('Remove track', 'wp-music-blocks')}
                                />
                            </div>
                        </div>
                        <div className="wpmb-track-editor-fields">
                            <TextControl
                                label={__('Title', 'wp-music-blocks')}
                                value={track.title}
                                onChange={(value) => updateTrack(index, 'title', value)}
                                size="compact"
                            />
                            <TextControl
                                label={__('Artist', 'wp-music-blocks')}
                                value={track.artist}
                                onChange={(value) => updateTrack(index, 'artist', value)}
                                size="compact"
                            />
                            <TextControl
                                label={__('Duration', 'wp-music-blocks')}
                                value={track.duration}
                                onChange={(value) => updateTrack(index, 'duration', value)}
                                placeholder="3:45"
                                size="compact"
                            />
                            <TextControl
                                label={__('URL', 'wp-music-blocks')}
                                value={track.url}
                                onChange={(value) => updateTrack(index, 'url', value)}
                                placeholder="https://..."
                                size="compact"
                            />
                            <TextControl
                                label={__('Cover URL', 'wp-music-blocks')}
                                value={track.coverUrl}
                                onChange={(value) => updateTrack(index, 'coverUrl', value)}
                                placeholder="https://..."
                                size="compact"
                            />
                        </div>
                    </div>
                ))}
            </div>

            <Button
                variant="secondary"
                onClick={addTrack}
                icon="plus"
                style={{ marginTop: '10px' }}
            >
                {__('Add Track', 'wp-music-blocks')}
            </Button>
        </div>
    );
}
