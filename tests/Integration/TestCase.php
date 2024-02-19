<?php

namespace leinonen\Yii2Algolia\Tests\Integration;

use Yii;
use yii\db\Schema;
use yii\db\Connection;
use leinonen\Yii2Algolia\AlgoliaComponent;
use leinonen\Yii2Algolia\Tests\Unit\TestCase as UnitTestCase;

class TestCase extends UnitTestCase
{
    protected $databaseConfig;

    protected function setUp(): void
    {
        $dsn = getenv('DB_DRIVER') . ':host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME');

        $this->databaseConfig = [
            'class' => Connection::class,
            'dsn' => $dsn,
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'charset' => 'utf8',
        ];

        $this->mockWebApplication([
            'bootstrap' => ['algolia'],
            'components' => [
                'algolia' => [
                    'class' => AlgoliaComponent::class,
                    'applicationId' => getenv('ALGOLIA_ID'),
                    'apiKey' => getenv('ALGOLIA_KEY'),
                ],
                'db' => $this->databaseConfig,
            ],
        ]);

        $this->migrateTestSchema();

        parent::setUp();
    }

    /**
     * Migrates the database schema for integration tests.
     */
    protected function migrateTestSchema()
    {
        if (Yii::$app->db->schema->getTableSchema('dummy_active_record_model') !== null) {
            Yii::$app->db->createCommand()->dropTable('dummy_active_record_model')->execute();
        }

        Yii::$app->db->createCommand()->createTable('dummy_active_record_model', [
            'id' => Schema::TYPE_PK,
            'test' => Schema::TYPE_STRING,
            'otherProperty' => Schema::TYPE_STRING,
        ])->execute();
    }
}
