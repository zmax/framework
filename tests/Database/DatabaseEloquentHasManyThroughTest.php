<?php

use Mockery as m;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatabaseEloquentHasManyThroughTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testRelationIsProperlyInitialized()
	{
		$relation = $this->getRelation();
		$model = m::mock('Illuminate\Database\Eloquent\Model');
		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array = array()) { return new Collection($array); });
		$model->shouldReceive('setRelation')->once()->with('foo', m::type('Illuminate\Database\Eloquent\Collection'));
		$models = $relation->initRelation(array($model), 'foo');

		$this->assertEquals(array($model), $models);
	}


	public function testEagerConstraintsAreProperlyAdded()
	{
		$relation = $this->getRelation();
		$relation->getQuery()->shouldReceive('whereIn')->once()->with('users.country_id', array(1, 2));
		$model1 = new EloquentHasManyThroughModelStub;
		$model1->id = 1;
		$model2 = new EloquentHasManyThroughModelStub;
		$model2->id = 2;
		$relation->addEagerConstraints(array($model1, $model2));
	}


	public function testModelsAreProperlyMatchedToParents()
	{
		$relation = $this->getRelation();

		$result1 = new EloquentHasManyThroughModelStub;
		$result1->country_id = 1;
		$result2 = new EloquentHasManyThroughModelStub;
		$result2->country_id = 2;
		$result3 = new EloquentHasManyThroughModelStub;
		$result3->country_id = 2;

		$model1 = new EloquentHasManyThroughModelStub;
		$model1->id = 1;
		$model2 = new EloquentHasManyThroughModelStub;
		$model2->id = 2;
		$model3 = new EloquentHasManyThroughModelStub;
		$model3->id = 3;

		$relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function($array) { return new Collection($array); });
		$models = $relation->match(array($model1, $model2, $model3), new Collection(array($result1, $result2, $result3)), 'foo');

		$this->assertEquals(1, $models[0]->foo[0]->country_id);
		$this->assertEquals(1, count($models[0]->foo));
		$this->assertEquals(2, $models[1]->foo[0]->country_id);
		$this->assertEquals(2, $models[1]->foo[1]->country_id);
		$this->assertEquals(2, count($models[1]->foo));
		$this->assertEquals(0, count($models[2]->foo));
	}


	public function testAllColumnsAreSelectedByDefault()
	{
		$select = array('posts.*', 'users.country_id');

		$baseBuilder = m::mock('Illuminate\Database\Query\Builder');

		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('newCollection')->once();

		$builder = $relation->getQuery();
		$builder->shouldReceive('getQuery')->andReturn($baseBuilder);
		$builder->shouldReceive('addSelect')->once()->with($select)->andReturn($builder);
		$builder->shouldReceive('getModels')->once()->andReturn(array());

		$relation->get();
	}


	public function testOnlyProperColumnsAreSelectedIfProvided()
	{
		$select = array('users.country_id');

		$baseBuilder = m::mock('Illuminate\Database\Query\Builder');
		$baseBuilder->columns = array('foo', 'bar');

		$relation = $this->getRelation();
		$relation->getRelated()->shouldReceive('newCollection')->once();

		$builder = $relation->getQuery();
		$builder->shouldReceive('getQuery')->andReturn($baseBuilder);
		$builder->shouldReceive('addSelect')->once()->with($select)->andReturn($builder);
		$builder->shouldReceive('getModels')->once()->andReturn(array());

		$relation->get();
	}


	public function testFirstMethod()
	{
		$relation = m::mock('Illuminate\Database\Eloquent\Relations\HasManyThrough[get]', $this->getRelationArguments());
		$relation->shouldReceive('get')->once()->andReturn(new Illuminate\Database\Eloquent\Collection(['first', 'second']));
		$relation->shouldReceive('take')->with(1)->once()->andReturn($relation);

		$this->assertEquals('first', $relation->first());
	}


	public function testFindMethod()
	{
		$relation = m::mock('Illuminate\Database\Eloquent\Relations\HasManyThrough[first]', $this->getRelationArguments());
		$relation->shouldReceive('where')->with('posts.id', '=', 'foo')->once()->andReturn($relation);
		$relation->shouldReceive('first')->once()->andReturn(new StdClass);

		$related = $relation->getRelated();
		$related->shouldReceive('getQualifiedKeyName')->once()->andReturn('posts.id');

		$relation->find('foo');
	}


	public function testFindManyMethod()
	{
		$relation = m::mock('Illuminate\Database\Eloquent\Relations\HasManyThrough[get]', $this->getRelationArguments());
		$relation->shouldReceive('get')->once()->andReturn(new Illuminate\Database\Eloquent\Collection(['first', 'second']));
		$relation->shouldReceive('whereIn')->with('posts.id', ['foo', 'bar'])->once()->andReturn($relation);

		$related = $relation->getRelated();
		$related->shouldReceive('getQualifiedKeyName')->once()->andReturn('posts.id');

		$relation->findMany(['foo', 'bar']);
	}


	public function testIgnoreSoftDeletingParent()
	{
		list($builder, $country,, $firstKey, $secondKey) = $this->getRelationArguments();
		$user = new EloquentHasManyThroughSoftDeletingModelStub;

		$builder->shouldReceive('whereNull')->with('users.deleted_at')->once()->andReturn($builder);

		$relation = new HasManyThrough($builder, $country, $user, $firstKey, $secondKey);
	}


	protected function getRelation()
	{
		list($builder, $country, $user, $firstKey, $secondKey) = $this->getRelationArguments();

		return new HasManyThrough($builder, $country, $user, $firstKey, $secondKey);
	}


	protected function getRelationArguments()
	{
		$builder = m::mock('Illuminate\Database\Eloquent\Builder');
		$builder->shouldReceive('join')->once()->with('users', 'users.id', '=', 'posts.user_id');
		$builder->shouldReceive('where')->with('users.country_id', '=', 1);

		$country = m::mock('Illuminate\Database\Eloquent\Model');
		$country->shouldReceive('getKey')->andReturn(1);
		$country->shouldReceive('getForeignKey')->andReturn('country_id');
		$user = m::mock('Illuminate\Database\Eloquent\Model');
		$user->shouldReceive('getTable')->andReturn('users');
		$user->shouldReceive('getQualifiedKeyName')->andReturn('users.id');
		$post = m::mock('Illuminate\Database\Eloquent\Model');
		$post->shouldReceive('getTable')->andReturn('posts');

		$builder->shouldReceive('getModel')->andReturn($post);

		$user->shouldReceive('getKey')->andReturn(1);
		$user->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
		$user->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

		return [$builder, $country, $user, 'country_id', 'user_id'];
	}

}

class EloquentHasManyThroughModelStub extends Illuminate\Database\Eloquent\Model {
	public $country_id = 'foreign.value';
}

class EloquentHasManyThroughSoftDeletingModelStub extends Illuminate\Database\Eloquent\Model {
	use SoftDeletes;
	public $table = 'users';
}
