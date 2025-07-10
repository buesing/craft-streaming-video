<?php
namespace buesing\streamingvideo;

use yii\base\Behavior;
use craft\elements\Asset;
use mikehaertl\shellcommand\Command;
use craft\helpers\FileHelper;
use Craft;

class StreamingVideoBehavior extends Behavior
{
    /**
     * @var Asset
     */
    public $owner;

    public function canStreamVideo(): bool
    {
        if ($this->owner instanceof Asset) {
            return strpos($this->owner->mimeType, 'video/') === 0;
        }
        return false;
    }

    /**
     * Returns the public URL to the HLS master playlist for this asset, or null if not available.
     */
    public function getHlsPlaylistUrl(): ?string
    {
        $volume = $this->owner->getVolume();
        $baseUrl = $volume->getRootUrl();
        if (!$baseUrl) {
            return null;
        }
        $hlsPath = '__hls__/' . $this->owner->uid . '/master.m3u8';
        return rtrim($baseUrl, '/') . '/' . ltrim($hlsPath, '/');
    }
    
    /**
     * Alias for getHlsPlaylistUrl for use as a property in Twig/GraphQL.
     */
    public function getHlsPlaylistUrlProperty(): ?string
    {
        return $this->getHlsPlaylistUrl();
    }

    public function prepareForStreaming(): bool
    {
        if (!$this->canStreamVideo()) {
            return false;
        }

        $inputPath = $this->owner->getCopyOfFile();
        $baseName = pathinfo($this->owner->filename, PATHINFO_FILENAME);

        $tempDir = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('hls_', true);
        if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            Craft::error("Failed to create temp directory: $tempDir", __METHOD__);
            return false;
        }

        $variants = [
            [
                'name' => '1080p',
                'resolution' => '1920x1080',
                'video_bitrate' => '5000k',
                'audio_bitrate' => '128k',
            ],
            [
                'name' => '720p',
                'resolution' => '1280x720',
                'video_bitrate' => '3000k',
                'audio_bitrate' => '128k',
            ],
            [
                'name' => '480p',
                'resolution' => '854x480',
                'video_bitrate' => '1500k',
                'audio_bitrate' => '96k',
            ],
        ];

        $playlistFiles = [];
        $processes = [];
        foreach ($variants as $variant) {
            $playlist = "{$variant['name']}.m3u8";
            $segmentPattern = "{$variant['name']}_%03d.ts";
            $cmd = 'ffmpeg -i ' . escapeshellarg($inputPath)
                . ' -c:v libx264 -b:v ' . $variant['video_bitrate']
                . ' -s ' . $variant['resolution']
                . ' -c:a aac -b:a ' . $variant['audio_bitrate']
                . ' -ac 2 -ar 48000' // ensure stereo, 48kHz audio
                . ' -hls_time 6 -hls_playlist_type vod'
                . ' -hls_segment_filename ' . escapeshellarg($tempDir . DIRECTORY_SEPARATOR . $segmentPattern)
                . ' ' . escapeshellarg($tempDir . DIRECTORY_SEPARATOR . $playlist);
            $processes[] = [
                'cmd' => $cmd,
                'playlist' => $playlist,
            ];
            $playlistFiles[] = $playlist;
        }

        foreach ($processes as $proc) {
            $command = new Command($proc['cmd']);
            if (!$command->execute()) {
                Craft::error("ffmpeg failed: " . $command->getError(), __METHOD__);
                FileHelper::removeDirectory($tempDir);
                @unlink($inputPath);
                return false;
            }
        }

        $masterPlaylist = "#EXTM3U\n\n";
        $bandwidths = [ '1080p' => 5000000, '720p' => 3000000, '480p' => 1500000 ];
        $resolutions = [ '1080p' => '1920x1080', '720p' => '1280x720', '480p' => '854x480' ];
        foreach ($playlistFiles as $playlist) {
            $name = basename($playlist, '.m3u8');
            $masterPlaylist .= "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidths[$name]},RESOLUTION={$resolutions[$name]}\n";
            $masterPlaylist .= "$playlist\n\n";
        }
        file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'master.m3u8', $masterPlaylist);

        $volume = $this->owner->getVolume();
        $fs = $volume->getFs();
        $hlsPath = '__hls__/' . $this->owner->uid . '/';
        $files = glob($tempDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            $targetPath = $hlsPath . basename($file);
            $stream = fopen($file, 'rb');
            $result = $fs->writeFileFromStream($targetPath, $stream);
            fclose($stream);
            if ($result === false) {
                Craft::error("Failed to write HLS file to volume: $targetPath", __METHOD__);
            } else {
                Craft::info("Successfully wrote HLS file to volume: $targetPath", __METHOD__);
            }
        }

        FileHelper::removeDirectory($tempDir);
        @unlink($inputPath);

        return true;
    }

    public function events()
    {
        return [
            Asset::EVENT_AFTER_SAVE => 'afterSave',
        ];
    }

    public function afterSave($event)
    {
        $asset = $this->owner;
        if ($this->canStreamVideo()) {
            \Craft::$app->queue->push(new \buesing\streamingvideo\PrepareHlsJob([
                'assetId' => $asset->id,
            ]));
        }
    }
} 
