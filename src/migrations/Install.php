<?php
namespace buesing\streamingvideo\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%streaming_video_conversion_status}}', [
            'id' => $this->primaryKey(),
            'assetId' => $this->integer()->notNull(),
            'status' => $this->enum('status', ['pending', 'processing', 'completed', 'failed'])->notNull()->defaultValue('pending'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(
            null,
            '{{%streaming_video_conversion_status}}',
            'assetId',
            '{{%assets}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex(null, '{{%streaming_video_conversion_status}}', 'assetId', true);
    }

    public function safeDown()
    {
        $this->dropTableIfExists('{{%streaming_video_conversion_status}}');
    }
}