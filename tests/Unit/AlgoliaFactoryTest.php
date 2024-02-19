<?php

namespace leinonen\Yii2Algolia\Tests\Unit;

use leinonen\Yii2Algolia\AlgoliaConfig;
use leinonen\Yii2Algolia\AlgoliaFactory;
use leinonen\Yii2Algolia\AlgoliaManager;
use leinonen\Yii2Algolia\Tests\Helpers\DummyActiveRecordModel;

class AlgoliaFactoryTest extends TestCase
{
    /** @test */
    public function test_it_can_create_a_new_AlgoliaManager()
    {
        $factory = new AlgoliaFactory();
        $manager = $factory->make(new AlgoliaConfig('app-id', 'secret'));

        $this->assertInstanceOf(AlgoliaManager::class, $manager);
    }

    /** @test */
    public function test_it_can_make_new_searchable_objects()
    {
        $factory = new AlgoliaFactory();
        $searchableModel = $factory->makeSearchableObject(DummyActiveRecordModel::class);
        $this->assertInstanceOf(DummyActiveRecordModel::class, $searchableModel);
    }


    public function test_it_should_throw_an_exception_if_not_a_searchable_class_is_given()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot initiate a class (leinonen\Yii2Algolia\AlgoliaFactory) which doesn\'t implement leinonen\Yii2Algolia\SearchableInterface');
        $factory = new AlgoliaFactory();
        $factory->makeSearchableObject(AlgoliaFactory::class);
    }
}
