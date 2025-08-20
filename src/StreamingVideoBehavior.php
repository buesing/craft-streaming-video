<?php

namespace buesing\streamingvideo;

use buesing\streamingvideo\records\ConversionStatusRecord;
use Craft;
use craft\elements\Asset;
use yii\base\Behavior;

class StreamingVideoBehavior extends Behavior
{
    private const HLS_DIRECTORY_PREFIX = '__hls__';

    private const MASTER_PLAYLIST_NAME = 'master.m3u8';

    /**
     * @var Asset
     */
    public $owner;

    public function canStreamVideo(): bool
    {
        if ($this->owner instanceof Asset) {
            return str_starts_with($this->owner->mimeType, 'video/');
        }

        return false;
    }

    public function getHlsPlaylistUrl(): ?string
    {
        if (! $this->canStreamVideo()) {
            return null;
        }

        $status = ConversionStatusRecord::findByAssetId($this->owner->id);
        if (! $status || $status->status !== 'completed') {
            return null;
        }

        $volume = $this->owner->getVolume();
        $baseUrl = $volume->getRootUrl();
        if (! $baseUrl) {
            return null;
        }

        $hlsPath = self::HLS_DIRECTORY_PREFIX.'/'.$this->owner->uid.'/'.self::MASTER_PLAYLIST_NAME;

        return rtrim($baseUrl, '/').'/'.ltrim($hlsPath, '/');
    }

    public function getHlsPlaylistUrlProperty(): ?string
    {
        return $this->getHlsPlaylistUrl();
    }

    public function events(): array
    {
        return [
            Asset::EVENT_AFTER_SAVE => 'afterSave',
            Asset::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function afterSave($event): void
    {
        $asset = $this->owner;
        if ($this->canStreamVideo()) {
            ConversionStatusRecord::setStatus($asset->id, 'pending');
            \Craft::$app->queue->push(new \buesing\streamingvideo\PrepareHlsJob([
                'assetId' => $asset->id,
            ]));
        }
    }

    public function afterDelete($event): void
    {
        $asset = $this->owner;
        if ($this->canStreamVideo() && $asset->uid) {
            $this->cleanupHlsFiles();
            $status = ConversionStatusRecord::findByAssetId($asset->id);
            if ($status) {
                $status->delete();
            }
        }
    }

    private function cleanupHlsFiles(): void
    {
        try {
            $volume = $this->owner->getVolume();
            $fs = $volume->getFs();
            $hlsPath = self::HLS_DIRECTORY_PREFIX.'/'.$this->owner->uid.'/';

            Craft::info("Attempting to clean up HLS files at path: {$hlsPath}", __METHOD__);

            // List all files in the HLS directory for this asset
            try {
                $files = $fs->getFileList($hlsPath);
                $deletedCount = 0;

                foreach ($files as $file) {
                    try {
                        $fs->deleteFile($hlsPath.$file['basename']);
                        $deletedCount++;
                        Craft::info("Deleted file: {$hlsPath}{$file['basename']}", __METHOD__);
                    } catch (\Exception $e) {
                        Craft::warning("Could not delete {$hlsPath}{$file['basename']}: ".$e->getMessage(), __METHOD__);
                    }
                }

                Craft::info("Deleted {$deletedCount} files from {$hlsPath}", __METHOD__);
            } catch (\Exception $e) {
                Craft::warning("Could not list contents of HLS directory {$hlsPath}: ".$e->getMessage(), __METHOD__);
            }

            // Try to remove the directory itself
            try {
                $fs->deleteDirectory($hlsPath);
                Craft::info("Deleted HLS directory: {$hlsPath}", __METHOD__);
            } catch (\Exception $e) {
                Craft::warning("Could not delete HLS directory {$hlsPath}: ".$e->getMessage(), __METHOD__);
            }

            Craft::info("HLS cleanup completed for asset {$this->owner->id}", __METHOD__);
        } catch (\Exception $e) {
            Craft::error("Failed to clean up HLS files for asset {$this->owner->id}: ".$e->getMessage(), __METHOD__);
        }
    }
}
