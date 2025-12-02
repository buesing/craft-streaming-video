<?php

namespace buesing\streamingvideo\migrations;

use craft\db\Migration;

/**
 * m251202_092049_add_source_file_hash_column migration.
 */
class m251202_092049_add_source_file_hash_column extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): bool
    {
        $this->addColumn(
            '{{%streaming_video_conversion_status}}',
            'sourceFileHash',
            $this->string(32)->null()
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): bool
    {
        $this->dropColumn('{{%streaming_video_conversion_status}}', 'sourceFileHash');

        return true;
    }
}
