<?php

namespace buesing\streamingvideo;

use buesing\streamingvideo\records\ConversionStatusRecord;
use Craft;
use craft\base\Element;
use craft\elements\Asset;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\DefineMetadataEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\gql\TypeManager;
use craft\web\View;
use GraphQL\Type\Definition\Type;
use mikehaertl\shellcommand\Command;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public string $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();

        $this->checkFFmpegAvailability();

        // Register the plugin template root for site templates
        \yii\base\Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['_streamingvideo'] = __DIR__.'/templates';
            }
        );

        Event::on(
            \craft\elements\Asset::class,
            \craft\elements\Asset::EVENT_DEFINE_BEHAVIORS,
            static function (\craft\events\DefineBehaviorsEvent $event) {
                $event->behaviors['streamingVideo'] = [
                    'class' => \buesing\streamingvideo\StreamingVideoBehavior::class,
                ];
            }
        );

        Event::on(
            TypeManager::class,
            TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
            function (DefineGqlTypeFieldsEvent $event) {
                if ($event->typeName === 'AssetInterface') {
                    $event->fields['hlsPlaylistUrl'] = [
                        'name' => 'hlsPlaylistUrl',
                        'type' => Type::string(),
                        'description' => 'HLS master playlist URL for streaming video',
                        'resolve' => function ($source) {
                            if ($source instanceof Asset && $source->hasMethod('getHlsPlaylistUrl')) {
                                return $source->getHlsPlaylistUrl();
                            }

                            return null;
                        },
                    ];
                }
            }
        );

        // Add streaming video metadata to asset control panel
        Event::on(
            Asset::class,
            Element::EVENT_DEFINE_METADATA,
            [$this, 'defineAssetMetadata']
        );

    }

    private function checkFFmpegAvailability(): void
    {
        try {
            $command = new Command('ffmpeg -version');
            $command->execute();

            if ($command->getExitCode() !== 0) {
                $this->showFFmpegWarning();
            }
        } catch (\Exception $e) {
            $this->showFFmpegWarning();
        }
    }

    private function showFFmpegWarning(): void
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getSession()->setError(
                'FFmpeg not found! The Streaming Video plugin requires FFmpeg to be installed on your system.'
            );
        }

        Craft::warning(
            'FFmpeg is not available on this system. The Streaming Video plugin requires FFmpeg for video processing.',
            'craft-streaming-video'
        );
    }

    public function defineAssetMetadata(DefineMetadataEvent $event): void
    {
        /** @var Asset $asset */
        $asset = $event->sender;

        // Only add metadata for video assets that can be streamed
        if ($asset->canStreamVideo()) {
            $event->metadata['Streaming Status'] = function () use ($asset) {
                $hlsUrl = $asset->getHlsPlaylistUrl();
                if ($hlsUrl) {
                    return '<span class="status green"></span> HLS Ready';
                }

                // Check conversion status from database
                $statusRecord = ConversionStatusRecord::findByAssetId($asset->id);
                if ($statusRecord) {
                    return match ($statusRecord->status) {
                        'processing' => '<span class="status orange"></span> Processing...',
                        'failed' => '<span class="status red"></span> Failed',
                        'completed' => '<span class="status green"></span> HLS Ready',
                        default => '<span class="status grey"></span> Queued'
                    };
                }

                return '<span class="status grey"></span> Queued';
            };
        }
    }
}
