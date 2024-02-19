<?php

namespace leinonen\Yii2Algolia\Tests\Unit\ActiveRecord;

use leinonen\Yii2Algolia\AlgoliaFactory;
use leinonen\Yii2Algolia\ActiveRecord\ActiveRecordFactory;
use leinonen\Yii2Algolia\Tests\Helpers\DummyActiveRecordModel;
use leinonen\Yii2Algolia\Tests\Unit\TestCase;

class ActiveRecordFactoryTest extends TestCase
{
    /** @test */
    public function test_it_creates_active_record_instances_with_given_class_names()
    {
        $factory = new ActiveRecordFactory();
        $createdActiveRecord = $factory->make(DummyActiveRecordModel::class);

        $this->assertInstanceOf(DummyActiveRecordModel::class, $createdActiveRecord);
    }


    public function test_it_throws_an_error_if_the_class_is_not_instance_of_ActiveRecordInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot initiate a class (leinonen\Yii2Algolia\AlgoliaFactory) which doesn\'t implement \yii\db\ActiveRecordInterface');
        $factory = new ActiveRecordFactory();
        $createdActiveRecord = $factory->make(AlgoliaFactory::class);
    }
}
