<?php

use Mockery as m;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DatabaseEloquentBelongsToManyTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testModelsAreProperlyHydrated()
    {
        $model1 = new EloquentBelongsToManyModelStub;
        $model1->fill(['name' => 'taylor', 'pivot_user_id' => 1, 'pivot_role_id' => 2]);
        $model2 = new EloquentBelongsToManyModelStub;
        $model2->fill(['name' => 'dayle', 'pivot_user_id' => 3, 'pivot_role_id' => 4]);
        $models = [$model1, $model2];

        $baseBuilder = m::mock('Illuminate\Database\Query\Builder');

        $relation = $this->getRelation();
        $relation->getParent()->shouldReceive('getConnectionName')->andReturn('foo.connection');
        $relation->getQuery()->shouldReceive('addSelect')->once()->with(['roles.*', 'user_role.user_id as pivot_user_id', 'user_role.role_id as pivot_role_id'])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('getModels')->once()->andReturn($models);
        $relation->getQuery()->shouldReceive('eagerLoadRelations')->once()->with($models)->andReturn($models);
        $relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array) { return new Collection($array); });
        $relation->getQuery()->shouldReceive('getQuery')->once()->andReturn($baseBuilder);
        $results = $relation->get();

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $results);

        // Make sure the foreign keys were set on the pivot models...
        $this->assertEquals('user_id', $results[0]->pivot->getForeignKey());
        $this->assertEquals('role_id', $results[0]->pivot->getOtherKey());

        $this->assertEquals('taylor', $results[0]->name);
        $this->assertEquals(1, $results[0]->pivot->user_id);
        $this->assertEquals(2, $results[0]->pivot->role_id);
        $this->assertEquals('foo.connection', $results[0]->pivot->getConnectionName());
        $this->assertEquals('dayle', $results[1]->name);
        $this->assertEquals(3, $results[1]->pivot->user_id);
        $this->assertEquals(4, $results[1]->pivot->role_id);
        $this->assertEquals('foo.connection', $results[1]->pivot->getConnectionName());
        $this->assertEquals('user_role', $results[0]->pivot->getTable());
        $this->assertTrue($results[0]->pivot->exists);
    }

    public function testTimestampsCanBeRetrievedProperly()
    {
        $model1 = new EloquentBelongsToManyModelStub;
        $model1->fill(['name' => 'taylor', 'pivot_user_id' => 1, 'pivot_role_id' => 2]);
        $model2 = new EloquentBelongsToManyModelStub;
        $model2->fill(['name' => 'dayle', 'pivot_user_id' => 3, 'pivot_role_id' => 4]);
        $models = [$model1, $model2];

        $baseBuilder = m::mock('Illuminate\Database\Query\Builder');

        $relation = $this->getRelation()->withTimestamps();
        $relation->getParent()->shouldReceive('getConnectionName')->andReturn('foo.connection');
        $relation->getQuery()->shouldReceive('addSelect')->once()->with([
            'roles.*',
            'user_role.user_id as pivot_user_id',
            'user_role.role_id as pivot_role_id',
            'user_role.created_at as pivot_created_at',
            'user_role.updated_at as pivot_updated_at',
        ])->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('getModels')->once()->andReturn($models);
        $relation->getQuery()->shouldReceive('eagerLoadRelations')->once()->with($models)->andReturn($models);
        $relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array) { return new Collection($array); });
        $relation->getQuery()->shouldReceive('getQuery')->once()->andReturn($baseBuilder);
        $results = $relation->get();
    }

    public function testModelsAreProperlyMatchedToParents()
    {
        $relation = $this->getRelation();

        $result1 = new EloquentBelongsToManyModelPivotStub;
        $result1->pivot->user_id = 1;
        $result2 = new EloquentBelongsToManyModelPivotStub;
        $result2->pivot->user_id = 2;
        $result3 = new EloquentBelongsToManyModelPivotStub;
        $result3->pivot->user_id = 2;

        $model1 = new EloquentBelongsToManyModelStub;
        $model1->id = 1;
        $model2 = new EloquentBelongsToManyModelStub;
        $model2->id = 2;
        $model3 = new EloquentBelongsToManyModelStub;
        $model3->id = 3;

        $relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array) { return new Collection($array); });
        $models = $relation->match([$model1, $model2, $model3], new Collection([$result1, $result2, $result3]), 'foo');

        $this->assertEquals(1, $models[0]->foo[0]->pivot->user_id);
        $this->assertEquals(1, count($models[0]->foo));
        $this->assertEquals(2, $models[1]->foo[0]->pivot->user_id);
        $this->assertEquals(2, $models[1]->foo[1]->pivot->user_id);
        $this->assertEquals(2, count($models[1]->foo));
        $this->assertEquals(0, count($models[2]->foo));
    }

    public function testRelationIsProperlyInitialized()
    {
        $relation = $this->getRelation();
        $relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array = []) { return new Collection($array); });
        $model = m::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('setRelation')->once()->with('foo', m::type('Illuminate\Database\Eloquent\Collection'));
        $models = $relation->initRelation([$model], 'foo');

        $this->assertEquals([$model], $models);
    }

    public function testEagerConstraintsAreProperlyAdded()
    {
        $relation = $this->getRelation();
        $relation->getQuery()->shouldReceive('whereIn')->once()->with('user_role.user_id', [1, 2]);
        $model1 = new EloquentBelongsToManyModelStub;
        $model1->id = 1;
        $model2 = new EloquentBelongsToManyModelStub;
        $model2->id = 2;
        $relation->addEagerConstraints([$model1, $model2]);
    }

    public function testAttachInsertsPivotTableRecord()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar']])->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $relation->attach(2, ['foo' => 'bar']);
    }

    public function testAttachMultipleInsertsPivotTableRecord()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('insert')->once()->with(
            [
                ['user_id' => 1, 'role_id' => 2, 'foo' => 'bar'],
                ['user_id' => 1, 'role_id' => 3, 'baz' => 'boom', 'foo' => 'bar'],
            ]
        )->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $relation->attach([2, 3 => ['baz' => 'boom']], ['foo' => 'bar']);
    }

    public function testAttachInsertsPivotTableRecordWithTimestampsWhenNecessary()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $relation->withTimestamps();
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'created_at' => 'time', 'updated_at' => 'time']])->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
        $relation->expects($this->once())->method('touchIfTouching');

        $relation->attach(2, ['foo' => 'bar']);
    }

    public function testAttachInsertsPivotTableRecordWithCustomTimestampColumns()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $relation->withTimestamps('custom_created_at', 'custom_updated_at');
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'custom_created_at' => 'time', 'custom_updated_at' => 'time']])->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
        $relation->expects($this->once())->method('touchIfTouching');

        $relation->attach(2, ['foo' => 'bar']);
    }

    public function testAttachInsertsPivotTableRecordWithACreatedAtTimestamp()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $relation->withPivot('created_at');
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'created_at' => 'time']])->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
        $relation->expects($this->once())->method('touchIfTouching');

        $relation->attach(2, ['foo' => 'bar']);
    }

    public function testAttachInsertsPivotTableRecordWithAnUpdatedAtTimestamp()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $relation->withPivot('updated_at');
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('insert')->once()->with([['user_id' => 1, 'role_id' => 2, 'foo' => 'bar', 'updated_at' => 'time']])->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->getParent()->shouldReceive('freshTimestamp')->once()->andReturn('time');
        $relation->expects($this->once())->method('touchIfTouching');

        $relation->attach(2, ['foo' => 'bar']);
    }

    public function testDetachRemovesPivotTableRecord()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
        $query->shouldReceive('whereIn')->once()->with('role_id', [1, 2, 3]);
        $query->shouldReceive('delete')->once()->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $this->assertTrue($relation->detach([1, 2, 3]));
    }

    public function testDetachWithSingleIDRemovesPivotTableRecord()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
        $query->shouldReceive('whereIn')->once()->with('role_id', [1]);
        $query->shouldReceive('delete')->once()->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $this->assertTrue($relation->detach([1]));
    }

    public function testDetachMethodClearsAllPivotRecordsWhenNoIDsAreGiven()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touchIfTouching'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
        $query->shouldReceive('whereIn')->never();
        $query->shouldReceive('delete')->once()->andReturn(true);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $relation->expects($this->once())->method('touchIfTouching');

        $this->assertTrue($relation->detach());
    }

    public function testFirstMethod()
    {
        $relation = m::mock('Illuminate\Database\Eloquent\Relations\BelongsToMany[get]', $this->getRelationArguments());
        $relation->shouldReceive('get')->once()->andReturn(new Illuminate\Database\Eloquent\Collection([new StdClass]));
        $relation->shouldReceive('take')->with(1)->once()->andReturn($relation);

        $this->assertTrue($relation->first() instanceof StdClass);
    }

    public function testFindMethod()
    {
        $relation = m::mock('Illuminate\Database\Eloquent\Relations\BelongsToMany[first]', $this->getRelationArguments());
        $relation->shouldReceive('first')->once()->andReturn(new StdClass);
        $relation->shouldReceive('where')->with('roles.id', '=', 'foo')->once()->andReturn($relation);

        $related = $relation->getRelated();
        $related->shouldReceive('getQualifiedKeyName')->once()->andReturn('roles.id');

        $this->assertTrue($relation->find('foo') instanceof StdClass);
    }

    public function testFindManyMethod()
    {
        $relation = m::mock('Illuminate\Database\Eloquent\Relations\BelongsToMany[get]', $this->getRelationArguments());
        $relation->shouldReceive('get')->once()->andReturn(new Illuminate\Database\Eloquent\Collection([new StdClass, new StdClass]));
        $relation->shouldReceive('whereIn')->with('roles.id', ['foo', 'bar'])->once()->andReturn($relation);

        $related = $relation->getRelated();
        $related->shouldReceive('getQualifiedKeyName')->once()->andReturn('roles.id');

        $result = $relation->findMany(['foo', 'bar']);

        $this->assertEquals(2, count($result));
        $this->assertTrue($result->first() instanceof StdClass);
    }

    public function testCreateMethodCreatesNewModelAndInsertsAttachmentRecord()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach'], $this->getRelationArguments());
        $relation->getRelated()->shouldReceive('newInstance')->once()->andReturn($model = m::mock('StdClass'))->with(['attributes']);
        $model->shouldReceive('save')->once();
        $model->shouldReceive('getKey')->andReturn('foo');
        $relation->expects($this->once())->method('attach')->with('foo', ['joining']);

        $this->assertEquals($model, $relation->create(['attributes'], ['joining']));
    }

    public function testFindOrNewMethodFindsModel()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['find'], $this->getRelationArguments());
        $relation->expects($this->once())->method('find')->with('foo')->will($this->returnValue($model = m::mock('StdClass')));
        $relation->getRelated()->shouldReceive('newInstance')->never();

        $this->assertTrue($relation->findOrNew('foo') instanceof StdClass);
    }

    public function testFindOrNewMethodReturnsNewModel()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['find'], $this->getRelationArguments());
        $relation->expects($this->once())->method('find')->with('foo')->will($this->returnValue(null));
        $relation->getRelated()->shouldReceive('newInstance')->once()->andReturn($model = m::mock('StdClass'));

        $this->assertTrue($relation->findOrNew('foo') instanceof StdClass);
    }

    public function testFirstOrNewMethodFindsFirstModel()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['where'], $this->getRelationArguments());
        $relation->expects($this->once())->method('where')->with(['foo'])->will($this->returnValue($relation->getQuery()));
        $relation->getQuery()->shouldReceive('first')->once()->andReturn($model = m::mock('StdClass'));
        $relation->getRelated()->shouldReceive('newInstance')->never();

        $this->assertTrue($relation->firstOrNew(['foo']) instanceof StdClass);
    }

    public function testFirstOrNewMethodReturnsNewModel()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['where'], $this->getRelationArguments());
        $relation->expects($this->once())->method('where')->with(['foo'])->will($this->returnValue($relation->getQuery()));
        $relation->getQuery()->shouldReceive('first')->once()->andReturn(null);
        $relation->getRelated()->shouldReceive('newInstance')->once()->andReturn($model = m::mock('StdClass'));

        $this->assertTrue($relation->firstOrNew(['foo']) instanceof StdClass);
    }

    public function testFirstOrCreateMethodFindsFirstModel()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['where', 'create'], $this->getRelationArguments());
        $relation->expects($this->once())->method('where')->with(['foo'])->will($this->returnValue($relation->getQuery()));
        $relation->getQuery()->shouldReceive('first')->once()->andReturn($model = m::mock('StdClass'));
        $relation->expects($this->never())->method('create')->with(['foo'])->will($this->returnValue(null));

        $this->assertTrue($relation->firstOrCreate(['foo']) instanceof StdClass);
    }

    public function testFirstOrCreateMethodReturnsNewModel()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['where', 'create'], $this->getRelationArguments());
        $relation->expects($this->once())->method('where')->with(['foo'])->will($this->returnValue($relation->getQuery()));
        $relation->getQuery()->shouldReceive('first')->once()->andReturn(null);
        $relation->expects($this->once())->method('create')->with(['foo'])->will($this->returnValue($model = m::mock('StdClass')));

        $this->assertTrue($relation->firstOrCreate(['foo']) instanceof StdClass);
    }

    public function testUpdateOrCreateMethodFindsFirstModelAndUpdates()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['where', 'create'], $this->getRelationArguments());
        $relation->expects($this->once())->method('where')->with(['foo'])->will($this->returnValue($relation->getQuery()));
        $relation->getQuery()->shouldReceive('first')->once()->andReturn($model = m::mock('StdClass'));
        $model->shouldReceive('fill')->once();
        $model->shouldReceive('save')->once();
        $relation->expects($this->never())->method('create')->with(['foo'])->will($this->returnValue(null));

        $this->assertTrue($relation->updateOrCreate(['foo']) instanceof StdClass);
    }

    public function testUpdateOrCreateMethodReturnsNewModel()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['where', 'create'], $this->getRelationArguments());
        $relation->expects($this->once())->method('where')->with(['bar'])->will($this->returnValue($relation->getQuery()));
        $relation->getQuery()->shouldReceive('first')->once()->andReturn(null);
        $relation->expects($this->once())->method('create')->with(['foo'])->will($this->returnValue($model = m::mock('StdClass')));

        $this->assertTrue($relation->updateOrCreate(['bar'], ['foo']) instanceof StdClass);
    }

    /**
     * @dataProvider syncMethodListProvider
     */
    public function testSyncMethodSyncsIntermediateTableWithGivenArray($list)
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
        $relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo([]), $this->equalTo(false));
        $relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
        $relation->getRelated()->shouldReceive('touches')->andReturn(false);
        $relation->getParent()->shouldReceive('touches')->andReturn(false);

        $this->assertEquals(['attached' => [4], 'detached' => [1], 'updated' => []], $relation->sync($list));
    }

    public function syncMethodListProvider()
    {
        return [
            [[2, 3, 4]],
            [['2', '3', '4']],
        ];
    }

    public function testSyncMethodSyncsIntermediateTableWithGivenArrayAndAttributes()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'updateExistingPivot'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
        $relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(['foo' => 'bar']), $this->equalTo(false));
        $relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(['baz' => 'qux']), $this->equalTo(false))->will($this->returnValue(true));
        $relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
        $relation->expects($this->once())->method('touchIfTouching');

        $this->assertEquals(['attached' => [4], 'detached' => [1], 'updated' => [3]], $relation->sync([2, 3 => ['baz' => 'qux'], 4 => ['foo' => 'bar']]));
    }

    public function testSyncMethodDoesntReturnValuesThatWereNotUpdated()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'updateExistingPivot'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
        $relation->expects($this->once())->method('attach')->with($this->equalTo(4), $this->equalTo(['foo' => 'bar']), $this->equalTo(false));
        $relation->expects($this->once())->method('updateExistingPivot')->with($this->equalTo(3), $this->equalTo(['baz' => 'qux']), $this->equalTo(false))->will($this->returnValue(false));
        $relation->expects($this->once())->method('detach')->with($this->equalTo([1]));
        $relation->expects($this->once())->method('touchIfTouching');

        $this->assertEquals(['attached' => [4], 'detached' => [1], 'updated' => []], $relation->sync([2, 3 => ['baz' => 'qux'], 4 => ['foo' => 'bar']]));
    }

    public function testTouchMethodSyncsTimestamps()
    {
        $relation = $this->getRelation();
        $relation->getRelated()->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
        $relation->getRelated()->shouldReceive('freshTimestamp')->andReturn(100);
        $relation->getRelated()->shouldReceive('getQualifiedKeyName')->andReturn('table.id');
        $relation->getQuery()->shouldReceive('select')->once()->with('table.id')->andReturn($relation->getQuery());
        $relation->getQuery()->shouldReceive('lists')->once()->with('id')->andReturn([1, 2, 3]);
        $relation->getRelated()->shouldReceive('newQuery')->once()->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('whereIn')->once()->with('id', [1, 2, 3])->andReturn($query);
        $query->shouldReceive('update')->once()->with(['updated_at' => 100]);

        $relation->touch();
    }

    public function testTouchIfTouching()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['touch', 'touchingParent'], $this->getRelationArguments());
        $relation->expects($this->once())->method('touchingParent')->will($this->returnValue(true));
        $relation->getParent()->shouldReceive('touch')->once();
        $relation->getParent()->shouldReceive('touches')->once()->with('relation_name')->andReturn(true);
        $relation->expects($this->once())->method('touch');

        $relation->touchIfTouching();
    }

    public function testSyncMethodConvertsCollectionToArrayOfKeys()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'formatSyncList'], $this->getRelationArguments());
        $query = m::mock('stdClass');
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder = m::mock('StdClass'));
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);
        $query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);

        $collection = m::mock('Illuminate\Database\Eloquent\Collection');
        $collection->shouldReceive('modelKeys')->once()->andReturn([1, 2, 3]);
        $relation->expects($this->once())->method('formatSyncList')->with([1, 2, 3])->will($this->returnValue([1 => [], 2 => [], 3 => []]));
        $relation->sync($collection);
    }

    public function testWherePivotParamsUsedForNewQueries()
    {
        $relation = $this->getMock('Illuminate\Database\Eloquent\Relations\BelongsToMany', ['attach', 'detach', 'touchIfTouching', 'formatSyncList'], $this->getRelationArguments());

        // we expect to call $relation->wherePivot()
        $relation->getQuery()->shouldReceive('where')->once()->andReturn($relation);

        // Our sync() call will produce a new query
        $mockQueryBuilder = m::mock('stdClass');
        $query = m::mock('stdClass');
        $relation->getQuery()->shouldReceive('getQuery')->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('newQuery')->once()->andReturn($query);

        // BelongsToMany::newPivotStatement() sets this
        $query->shouldReceive('from')->once()->with('user_role')->andReturn($query);

        // BelongsToMany::newPivotQuery() sets this
        $query->shouldReceive('where')->once()->with('user_id', 1)->andReturn($query);

        // This is our test! The wherePivot() params also need to be called
        $query->shouldReceive('where')->once()->with('foo', '=', 'bar')->andReturn($query);

        // This is so $relation->sync() works
        $query->shouldReceive('lists')->once()->with('role_id')->andReturn([1, 2, 3]);
        $relation->expects($this->once())->method('formatSyncList')->with([1, 2, 3])->will($this->returnValue([1 => [], 2 => [], 3 => []]));

        $relation = $relation->wherePivot('foo', '=', 'bar'); // these params are to be stored
        $relation->sync([1, 2, 3]); // triggers the whole process above
    }

    public function getRelation()
    {
        list($builder, $parent) = $this->getRelationArguments();

        return new BelongsToMany($builder, $parent, 'user_role', 'user_id', 'role_id', 'relation_name');
    }

    public function getRelationArguments()
    {
        $parent = m::mock('Illuminate\Database\Eloquent\Model');
        $parent->shouldReceive('getKey')->andReturn(1);
        $parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
        $parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

        $builder = m::mock('Illuminate\Database\Eloquent\Builder');
        $related = m::mock('Illuminate\Database\Eloquent\Model');
        $builder->shouldReceive('getModel')->andReturn($related);

        $related->shouldReceive('getTable')->andReturn('roles');
        $related->shouldReceive('getKeyName')->andReturn('id');
        $related->shouldReceive('newPivot')->andReturnUsing(function () {
            $reflector = new ReflectionClass('Illuminate\Database\Eloquent\Relations\Pivot');

            return $reflector->newInstanceArgs(func_get_args());
        });

        $builder->shouldReceive('join')->once()->with('user_role', 'roles.id', '=', 'user_role.role_id');
        $builder->shouldReceive('where')->once()->with('user_role.user_id', '=', 1);

        return [$builder, $parent, 'user_role', 'user_id', 'role_id', 'relation_name'];
    }
}

class EloquentBelongsToManyModelStub extends Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];
}

class EloquentBelongsToManyModelPivotStub extends Illuminate\Database\Eloquent\Model
{
    public $pivot;
    public function __construct()
    {
        $this->pivot = new EloquentBelongsToManyPivotStub;
    }
}

class EloquentBelongsToManyPivotStub
{
    public $user_id;
}
