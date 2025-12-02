<?php

namespace buesing\streamingvideo\Utilities;

use craft\elements\Asset;

class VideoFileHasher
{
    public static function computeHash(Asset $asset): string
    {
        return md5($asset->size.'|'.$asset->dateModified->getTimestamp());
    }
}
