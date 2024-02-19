<?php

namespace leinonen\Yii2Algolia\Tests\Unit;

use Yii;
use Mockery as m;
use Algolia\AlgoliaSearch\SearchClient;
use leinonen\Yii2Algolia\AlgoliaFactory;
use leinonen\Yii2Algolia\AlgoliaManager;
use leinonen\Yii2Algolia\AlgoliaComponent;
use leinonen\Yii2Algolia\ActiveRecord\ActiveQueryChunker;
use leinonen\Yii2Algolia\ActiveRecord\ActiveRecordFactory;
use leinonen\Yii2Algolia\Tests\Unit\TestCase;

class AlgoliaComponentTest extends TestCase
{
    /**
     * @var SearchClient
     */
    private $mockAlgoliaClient;

    private $algoliaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $mockAlgoliaFactory = m::mock(AlgoliaFactory::class);
        $this->mockAlgoliaClient = m::mock(SearchClient::class);

        $this->algoliaManager = new AlgoliaManager(
            $this->mockAlgoliaClient,
            new ActiveRecordFactory(),
            new ActiveQueryChunker()
        );

        $mockAlgoliaFactory->shouldReceive('make')->andReturn($this->algoliaManager);
        Yii::$container->set(AlgoliaFactory::class, $mockAlgoliaFactory);

        $this->mockWebApplication([
            'bootstrap' => ['algolia'],
            'components' => [
                'algolia' => [
                    'class' => AlgoliaComponent::class,
                    'applicationId' => 'test',
                    'apiKey' => 'secret',
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        m::close();

        // The mockWebApplication is called after a mock has been already set to the container
        // So we need to manually clear that.
        Yii::$container->clear(AlgoliaFactory::class);

        parent::tearDown();
    }

    /** @test */
    public function test_it_accessible_like_proper_yii2_component()
    {
        $this->assertInstanceOf(AlgoliaComponent::class, Yii::$app->algolia);
    }

    /** @test */
    public function test_it_registers_AlgoliaManager_to_di_container_properly()
    {
        $manager = Yii::$container->get(AlgoliaManager::class);
        $this->assertInstanceOf(AlgoliaManager::class, $manager);

        /** @var SearchClient $client */
        $client = $manager->getClient();
        $this->assertEquals($this->mockAlgoliaClient, $client);
    }

    /** @test */
    public function test_it_delegates_the_methods_to_AlgoliaManager()
    {
        $this->assertEquals($this->mockAlgoliaClient, Yii::$app->algolia->getClient());
    }


    public function test_it_throws_an_error_if_applicationId_is_not_specified()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('applicationId and apiKey are required');
        $this->mockApplication([
            'bootstrap' => ['algolia'],
            'components' => [
                'algolia' => [
                    'class' => AlgoliaComponent::class,
                    'apiKey' => 'secret',
                ],
            ],
        ]);
    }


    public function test_it_throws_an_error_if_apiKey_is_not_specified()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->expectExceptionMessage('applicationId and apiKey are required');
        $this->mockApplication([
            'bootstrap' => ['algolia'],
            'components' => [
                'algolia' => [
                    'class' => AlgoliaComponent::class,
                    'applicationId' => 'app-id',
                ],
            ],
        ]);
    }
}
