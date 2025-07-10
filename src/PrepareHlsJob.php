<?php
namespace buesing\streamingvideo;

use craft\queue\BaseJob;
use craft\elements\Asset;
use Craft;

class PrepareHlsJob extends BaseJob
{
    public int $assetId;

    public function execute($queue): void
    {
      
        Craft::info('PrepareHlsJob started for asset ID: ' . $this->assetId, __METHOD__);
        $asset = Asset::find()->id($this->assetId)->one();
        if (!$asset) {
            Craft::error("PrepareHlsJob: Asset not found for ID {$this->assetId}", __METHOD__);
            return;
        }
        if (!$asset->canStreamVideo()) {
            Craft::info("PrepareHlsJob: Asset {$this->assetId} is not a video.", __METHOD__);
            return;
        }
        if (!$asset->prepareForStreaming()) {
            Craft::error("PrepareHlsJob: Failed to prepare HLS for asset {$this->assetId}", __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Prepare HLS streaming for video asset';
    }
} 
