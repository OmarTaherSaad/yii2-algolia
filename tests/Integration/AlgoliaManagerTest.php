<?php

namespace leinonen\Yii2Algolia\Tests\Integration;

use Yii;
use Mockery as m;
use leinonen\Yii2Algolia\AlgoliaManager;
use leinonen\Yii2Algolia\AlgoliaComponent;
use leinonen\Yii2Algolia\SearchableInterface;
use leinonen\Yii2Algolia\Tests\Helpers\DummyModel;
use leinonen\Yii2Algolia\Tests\Helpers\DummyActiveRecordModel;

class AlgoliaManagerTest extends TestCase
{
    /**
     * @var AlgoliaManager
     */
    private $algoliaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->algoliaManager = Yii::$container->get(AlgoliaManager::class);
    }

    /** @test */
    public function test_it_can_index_an_searchable_object()
    {
        $index = $this->createDummyObjectToIndex();
        $searchResult = $index->search('otherProperty');

        $this->deleteIndex($index);
        $this->assertCount(1, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_index_multiple_objects_in_a_batch()
    {
        $dummyModel1 = $this->makeDummyModel(1);
        $dummyModel2 = $this->makeDummyModel(2);
        $indexName = $dummyModel1->getIndices()[0];

        $pushResponse = $this->algoliaManager->pushMultipleToIndices([$dummyModel1, $dummyModel2]);

        $index = $this->algoliaManager->initIndex($indexName);
        $index->waitTask($pushResponse[$indexName]['taskID']);

        $this->assertArrayHasKey('objectIDs', $pushResponse[$index->indexName]);
        $this->assertEquals(['1', '2'], $pushResponse[$index->indexName]['objectIDs']);

        $searchResult = $index->search('otherProperty');

        $this->deleteIndex($index);
        $this->assertCount(2, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_update_an_existing_searchable_object()
    {
        $objectId = 1;

        $index = $this->createDummyObjectToIndex($objectId);
        $dummyObject = $this->makeDummyModel($objectId);
        $dummyObject->otherProperty = 'A new text for property';

        $updateResponse = $this->algoliaManager->updateInIndices($dummyObject);
        $index->waitTask($updateResponse[$index->indexName]['taskID']);

        $this->assertArrayHasKey('objectID', $updateResponse[$index->indexName]);
        $this->assertArrayHasKey('updatedAt', $updateResponse[$index->indexName]);
        $this->assertEquals("{$objectId}", $updateResponse[$index->indexName]['objectID']);

        $searchResult = $index->search('A new text for property');

        $this->deleteIndex($index);
        $this->assertCount(1, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_update_multiple_existing_searchable_objects()
    {
        $index = $this->createDummyObjectToIndex(1);
        // This dummy object uses the same index so no need to get it from the method.
        $this->createDummyObjectToIndex(2);

        $dummyObject1 = $this->makeDummyModel(1);
        $dummyObject1->otherProperty = 'A new text for property';

        $dummyObject2 = $this->makeDummyModel(2);
        $dummyObject2->otherProperty = 'A new text for property';

        $updateResponse = $this->algoliaManager->updateMultipleInIndices([$dummyObject1, $dummyObject2]);
        $index->waitTask($updateResponse[$index->indexName]['taskID']);

        $this->assertArrayHasKey('objectIDs', $updateResponse[$index->indexName]);
        $this->assertEquals(['1', '2'], $updateResponse[$index->indexName]['objectIDs']);

        $searchResult = $index->search('A new text for property');

        $this->deleteIndex($index);
        $this->assertCount(2, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_remove_an_existing_searchable_object_from_indices()
    {
        $objectID = 1;
        $index = $this->createDummyObjectToIndex($objectID);
        $dummyObject1 = $this->makeDummyModel($objectID);

        $deleteResponse = $this->algoliaManager->removeFromIndices($dummyObject1);
        $this->assertArrayHasKey('deletedAt', $deleteResponse[$index->indexName]);

        $index->waitTask($deleteResponse[$index->indexName]['taskID']);
        $searchResult = $index->search('test');

        $this->deleteIndex($index);
        $this->assertCount(0, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_remove_multiple_existing_searchable_objects_from_indices()
    {
        $objectID1 = 1;
        $index = $this->createDummyObjectToIndex($objectID1);
        $dummyObject1 = $this->makeDummyModel($objectID1);

        $objectID2 = 2;
        // This dummy object uses the same index so no need to get it from the method.
        $this->createDummyObjectToIndex($objectID2);
        $dummyObject2 = $this->makeDummyModel($objectID2);

        $deleteResponse = $this->algoliaManager->removeMultipleFromIndices([$dummyObject1, $dummyObject2]);

        $this->assertArrayHasKey('objectIDs', $deleteResponse[$index->indexName]);
        $this->assertEquals(['1', '2'], $deleteResponse[$index->indexName]['objectIDs']);

        $index->waitTask($deleteResponse[$index->indexName]['taskID']);
        $searchResult = $index->search('test');

        $this->deleteIndex($index);
        $this->assertCount(0, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_do_searches()
    {
        $activeRecord1 = new DummyActiveRecordModel();
        $activeRecord1->test = 'test';
        $activeRecord1->save();

        $activeRecord2 = new DummyActiveRecordModel();
        $activeRecord2->test = 'test';
        $activeRecord2->save();

        $index = $this->addSearchableObjectToIndex($activeRecord1);
        $this->addSearchableObjectToIndex($activeRecord2);

        $searchResult = $this->algoliaManager->search(DummyActiveRecordModel::class, 'test');

        $this->deleteIndex($index);
        $this->assertCount(2, $searchResult['DummyActiveRecordModel']['hits']);
        $this->assertEquals('test', $searchResult['DummyActiveRecordModel']['query']);
    }

    /** @test */
    public function test_it_can_do_searches_with_additional_parameters()
    {
        $activeRecord1 = new DummyActiveRecordModel();
        $activeRecord1->test = 'test';
        $activeRecord1->save();

        $activeRecord2 = new DummyActiveRecordModel();
        $activeRecord2->test = 'test';
        $activeRecord2->save();

        $index = $this->addSearchableObjectToIndex($activeRecord1);
        $this->addSearchableObjectToIndex($activeRecord2);

        $searchResult = $this->algoliaManager->search(DummyActiveRecordModel::class, 'test', ['hitsPerPage' => 1]);

        $this->deleteIndex($index);
        $this->assertCount(1, $searchResult['DummyActiveRecordModel']['hits']);
        $this->assertEquals('test', $searchResult['DummyActiveRecordModel']['query']);
        $this->assertEquals(1, $searchResult['DummyActiveRecordModel']['hitsPerPage']);
    }

    /** @test */
    public function test_it_can_reindex_the_given_active_record_class()
    {
        $activeRecord1 = new DummyActiveRecordModel();
        $activeRecord1->test = 'test';
        $activeRecord1->save();

        $activeRecord2 = new DummyActiveRecordModel();
        $activeRecord2->test = 'test';
        $activeRecord2->save();

        $indexName = $activeRecord1->getIndices()[0];
        $index = $this->algoliaManager->initIndex($indexName);

        // Add dummy object to the index so it exists for the reindex operation.
        $response = $index->saveObject(['dummy' => 'dummy'], 'dummy');
        $index->waitTask($response['taskID']);

        $response = $this->algoliaManager->reindex(DummyActiveRecordModel::class);
        $index->waitTask($response[$index->indexName]['taskID']);

        $searchResult = $index->search('test');

        $this->deleteIndex($index);
        $this->assertCount(2, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_reindex_the_given_active_query()
    {
        $activeRecord1 = new DummyActiveRecordModel();
        $activeRecord1->test = 'reindex';
        $activeRecord1->otherProperty = 'indexable';
        $activeRecord1->save();

        $activeRecord2 = new DummyActiveRecordModel();
        $activeRecord2->test = 'reindex';
        $activeRecord2->otherProperty = 'notIndexable';
        $activeRecord2->save();

        $indexName = $activeRecord1->getIndices()[0];
        $index = $this->algoliaManager->initIndex($indexName);

        // Add dummy object to the index so it exists for the reindex operation.
        $response = $index->saveObject(['dummy' => 'dummy'], 'dummy');
        $index->waitTask($response['taskID']);

        $activeQuery = DummyActiveRecordModel::find()->where(['otherProperty' => 'indexable']);

        $response = $this->algoliaManager->reindexByActiveQuery($activeQuery);
        $index->waitTask($response[$index->indexName]['taskID']);

        $searchResult = $index->search('reindex');

        $this->deleteIndex($index);
        $this->assertCount(1, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_reindex_explicitly_given_searchable_objects()
    {
        $activeRecord1 = new DummyActiveRecordModel();
        $activeRecord1->test = 'test';
        $activeRecord1->save();

        $activeRecord2 = new DummyActiveRecordModel();
        $activeRecord2->test = 'test';
        $activeRecord2->save();

        $indexName = $activeRecord1->getIndices()[0];
        $index = $this->algoliaManager->initIndex($indexName);

        // Add dummy object to the index so it exists for the reindex operation.
        $response = $index->saveObject(['dummy' => 'dummy'], 'dummy');
        $index->waitTask($response['taskID']);

        $response = $this->algoliaManager->reindexOnly([$activeRecord1, $activeRecord2]);
        $index->waitTask($response[$index->indexName]['taskID']);

        $searchResult = $index->search('test');

        $this->deleteIndex($index);
        $this->assertCount(2, $searchResult['hits']);
    }

    /** @test */
    public function test_it_can_clear_indices_for_given_active_record_class()
    {
        $activeRecord1 = new DummyActiveRecordModel();
        $activeRecord1->test = 'test';
        $activeRecord1->save();

        $activeRecord2 = new DummyActiveRecordModel();
        $activeRecord2->test = 'test';
        $activeRecord2->save();

        $index = $this->addSearchableObjectToIndex($activeRecord1);
        $this->addSearchableObjectToIndex($activeRecord2);

        $response = $this->algoliaManager->clearIndices(DummyActiveRecordModel::class);
        $index->waitTask($response[$index->indexName]['taskID']);

        $searchResult = $index->search('test');

        $this->deleteIndex($index);
        $this->assertCount(0, $searchResult['hits']);
    }

    /** @test */
    public function test_it_uses_right_index_according_to_given_env()
    {
        // Clean up.
        $this->destroyApplication();
        $this->mockWebApplication([
            'bootstrap' => ['algolia'],
            'components' => [
                'algolia' => [
                    'class' => AlgoliaComponent::class,
                    'applicationId' => getenv('ALGOLIA_ID'),
                    'apiKey' => getenv('ALGOLIA_KEY'),
                    'env' => 'test',
                ],
            ],
        ]);

        $algoliaManager = Yii::$container->get(AlgoliaManager::class);

        $searchableObject = $this->getMockActiveRecord();
        $searchableObject->shouldReceive('getIndices')->andReturn(['index']);

        $pushResponse = $algoliaManager->pushToIndices($searchableObject);

        $this->assertArrayHasKey('test_index', $pushResponse);
        $this->assertEquals('test', $algoliaManager->getEnv());
        $this->deleteIndex($algoliaManager->initIndex('test_index'));
    }

    /**
     * Creates one dummy object to a index.
     *
     * @param int $objectId
     *
     * @return \Algolia\AlgoliaSearch\SearchIndex
     */
    private function createDummyObjectToIndex($objectId = 1)
    {
        $searchableObject = $this->makeDummyModel($objectId);

        return $this->addSearchableObjectToIndex($searchableObject);
    }

    /**
     * Adds Searchable object to algolia index and makes the necessary assertions.
     *
     * @param SearchableInterface $searchableObject
     *
     * @return \Algolia\AlgoliaSearch\SearchIndex
     */
    private function addSearchableObjectToIndex($searchableObject)
    {
        $indexName = $searchableObject->getIndices()[0];

        $pushResponse = $this->algoliaManager->pushToIndices($searchableObject);

        $this->assertArrayHasKey('objectID', $pushResponse[$indexName]);
        $this->assertArrayHasKey('updatedAt', $pushResponse[$indexName]);
        $this->assertEquals("{$searchableObject->getObjectID()}", $pushResponse[$indexName]['objectID']);

        $index = $this->algoliaManager->initIndex($indexName);
        $index->waitTask($pushResponse[$indexName]['taskID']);

        return $index;
    }

    /**
     * Deletes an Algolia index and asserts that it is successful.
     *
     * @param $index
     */
    private function deleteIndex($index)
    {
        $deleteResult = $this->algoliaManager->deleteIndex($index->indexName);
        $this->assertArrayHasKey('deletedAt', $deleteResult);
        $this->assertArrayHasKey('taskID', $deleteResult);
    }

    /**
     * Returns a dummy Yii's Base Mdoel object.
     *
     * @param int $objectId
     *
     * @return SearchableInterface
     */
    private function makeDummyModel($objectId = 1)
    {
        return new DummyModel([
            'id' => $objectId,
            'test' => 'test',
            'otherProperty' => 'otherProperty',
        ]);
    }

    /**
     * Returns a dummy ActiveRecord object.
     *
     * @param int $objectId
     *
     * @return SearchableInterface
     */
    private function getMockActiveRecord($objectId = 1)
    {
        // ActiveRecord needs to mocked because of the database.
        $searchableObject = m::mock(DummyActiveRecordModel::class);
        $searchableObject->shouldReceive('attributes')->andReturn([
            'test',
            'otherProperty',
        ]);

        $searchableObject->shouldReceive('getObjectID')->andReturn($objectId);
        $searchableObject->makePartial();
        $searchableObject->test = 'test';
        $searchableObject->otherProperty = 'otherProperty';

        return $searchableObject;
    }
}
