<?php
namespace buesing\streamingvideo;

use craft\events\DefineGqlTypeFieldsEvent;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\Type;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\web\View;
use craft\elements\Asset;
use yii\base\Event;
use buesing\streamingvideo\fields\StreamingVideoField;
use Craft;
use mikehaertl\shellcommand\Command;

class Plugin extends \craft\base\Plugin
{
    public function init()
    {
        parent::init();

        $this->checkFFmpegAvailability();

        // Register the plugin template root for site templates
        \yii\base\Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['_streamingvideo'] = __DIR__ . '/templates';
            }
        );

        Event::on(
            \craft\elements\Asset::class,
            \craft\elements\Asset::EVENT_DEFINE_BEHAVIORS,
            static function(\craft\events\DefineBehaviorsEvent $event) {
                $event->behaviors['streamingVideo'] = [
                    'class' => \buesing\streamingvideo\StreamingVideoBehavior::class
                ];
            }
        );

        // Register the StreamingVideoField field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = StreamingVideoField::class;
            }
        );

        Event::on(
            TypeManager::class,
            TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
            function(DefineGqlTypeFieldsEvent $event) {
                if ($event->typeName === 'AssetInterface') {
                    $event->fields['hlsPlaylistUrl'] = [
                        'name' => 'hlsPlaylistUrl',
                        'type' => Type::string(),
                        'description' => 'HLS master playlist URL for streaming video',
                        'resolve' => function($source) {
                            if ($source instanceof Asset && $source->hasMethod('getHlsPlaylistUrl')) {
                                return $source->getHlsPlaylistUrl();
                            }
                            return null;
                        },
                    ];
                }
            }
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
}
