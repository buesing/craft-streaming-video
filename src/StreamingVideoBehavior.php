<?php
namespace buesing\streamingvideo;

use yii\base\Behavior;
use craft\elements\Asset;
use mikehaertl\shellcommand\Command;
use craft\helpers\FileHelper;
use Craft;
use buesing\streamingvideo\records\ConversionStatusRecord;

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
        if (!$this->canStreamVideo()) {
            return null;
        }

        $status = ConversionStatusRecord::findByAssetId($this->owner->id);
        if (!$status || $status->status !== 'completed') {
            return null;
        }

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


    public function events()
    {
        return [
            Asset::EVENT_AFTER_SAVE => 'afterSave',
            Asset::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function afterSave($event)
    {
        $asset = $this->owner;
        if ($this->canStreamVideo()) {
            ConversionStatusRecord::setStatus($asset->id, 'pending');
            \Craft::$app->queue->push(new \buesing\streamingvideo\PrepareHlsJob([
                'assetId' => $asset->id,
            ]));
        }
    }

    public function afterDelete($event)
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

    /**
     * Removes the HLS folder and all its contents for this asset.
     */
    private function cleanupHlsFiles(): void
    {
        try {
            $volume = $this->owner->getVolume();
            $fs = $volume->getFs();
            $hlsPath = '__hls__/' . $this->owner->uid . '/';
            
            Craft::info("Attempting to clean up HLS files at path: {$hlsPath}", __METHOD__);
            
            // List all files in the HLS directory for this asset
            try {
                $files = $fs->getFileList($hlsPath);
                $deletedCount = 0;
                
                foreach ($files as $file) {
                    try {
                        $fs->deleteFile($hlsPath . $file['basename']);
                        $deletedCount++;
                        Craft::info("Deleted file: {$hlsPath}{$file['basename']}", __METHOD__);
                    } catch (\Exception $e) {
                        Craft::warning("Could not delete {$hlsPath}{$file['basename']}: " . $e->getMessage(), __METHOD__);
                    }
                }
                
                Craft::info("Deleted {$deletedCount} files from {$hlsPath}", __METHOD__);
            } catch (\Exception $e) {
                Craft::warning("Could not list contents of HLS directory {$hlsPath}: " . $e->getMessage(), __METHOD__);
            }
            
            // Try to remove the directory itself
            try {
                $fs->deleteDirectory($hlsPath);
                Craft::info("Deleted HLS directory: {$hlsPath}", __METHOD__);
            } catch (\Exception $e) {
                Craft::warning("Could not delete HLS directory {$hlsPath}: " . $e->getMessage(), __METHOD__);
            }
            
            Craft::info("HLS cleanup completed for asset {$this->owner->id}", __METHOD__);
        } catch (\Exception $e) {
            Craft::error("Failed to clean up HLS files for asset {$this->owner->id}: " . $e->getMessage(), __METHOD__);
        }
    }
} 
