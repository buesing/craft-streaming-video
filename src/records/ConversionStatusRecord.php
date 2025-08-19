<?php

namespace buesing\streamingvideo\records;

use craft\db\ActiveRecord;

class ConversionStatusRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%streaming_video_conversion_status}}';
    }

    public static function findByAssetId(int $assetId): ?self
    {
        return static::findOne(['assetId' => $assetId]);
    }

    public static function setStatus(int $assetId, string $status): void
    {
        $record = static::findByAssetId($assetId);
        if (! $record) {
            $record = new static;
            $record->assetId = $assetId;
        }
        $record->status = $status;
        $record->save();
    }
}
