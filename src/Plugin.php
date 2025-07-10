<?php
namespace buesing\streamingvideo;

use craft\events\DefineGqlTypeFieldsEvent;
use craft\gql\TypeManager;
use GraphQL\Type\Definition\Type;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use craft\elements\Asset;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public function init()
    {
        parent::init();

        // Register the plugin template root for site templates
        \yii\base\Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['streamingvideo'] = __DIR__ . '/templates';
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
                            if ($source instanceof Asset && $source->hasMethod('getStreamingVideo')) {
                                return $source->getStreamingVideo()->getHlsPlaylistUrl();
                            }
                            return null;
                        },
                    ];
                }
            }
        );
    }
}
