<?php

use Mockery as m;

class QueueDatabaseQueueTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testPushProperlyPushesJobOntoDatabase()
    {
        $queue = $this->getMock('Illuminate\Queue\DatabaseQueue', ['getTime'], [$database = m::mock('Illuminate\Database\Connection'), 'table', 'default']);
        $queue->expects($this->any())->method('getTime')->will($this->returnValue('time'));
        $database->shouldReceive('table')->with('table')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) {
            $this->assertEquals('default', $array['queue']);
            $this->assertEquals(json_encode(['job' => 'foo', 'data' => ['data']]), $array['payload']);
            $this->assertEquals(0, $array['attempts']);
            $this->assertEquals(0, $array['reserved']);
            $this->assertNull($array['reserved_at']);
            $this->assertTrue(is_integer($array['available_at']));
        });

        $queue->push('foo', ['data']);
    }

    public function testDelayedPushProperlyPushesJobOntoDatabase()
    {
        $queue = $this->getMock(
            'Illuminate\Queue\DatabaseQueue',
            ['getTime'],
            [$database = m::mock('Illuminate\Database\Connection'), 'table', 'default']
        );
        $queue->expects($this->any())->method('getTime')->will($this->returnValue('time'));
        $database->shouldReceive('table')->with('table')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('insertGetId')->once()->andReturnUsing(function ($array) {
            $this->assertEquals('default', $array['queue']);
            $this->assertEquals(json_encode(['job' => 'foo', 'data' => ['data']]), $array['payload']);
            $this->assertEquals(0, $array['attempts']);
            $this->assertEquals(0, $array['reserved']);
            $this->assertNull($array['reserved_at']);
            $this->assertTrue(is_integer($array['available_at']));
        });

        $queue->later(10, 'foo', ['data']);
    }

    public function testBulkBatchPushesOntoDatabase()
    {
        $database = m::mock('Illuminate\Database\Connection');
        $queue = $this->getMock('Illuminate\Queue\DatabaseQueue', ['getTime', 'getAvailableAt'], [$database, 'table', 'default']);
        $queue->expects($this->any())->method('getTime')->will($this->returnValue('created'));
        $queue->expects($this->any())->method('getAvailableAt')->will($this->returnValue('available'));
        $database->shouldReceive('table')->with('table')->andReturn($query = m::mock('StdClass'));
        $query->shouldReceive('insert')->once()->andReturnUsing(function ($records) {
            $this->assertEquals([[
                'queue' => 'queue',
                'payload' => json_encode(['job' => 'foo', 'data' => ['data']]),
                'attempts' => 0,
                'reserved' => 0,
                'reserved_at' => null,
                'available_at' => 'available',
                'created_at' => 'created',
            ], [
                'queue' => 'queue',
                'payload' => json_encode(['job' => 'bar', 'data' => ['data']]),
                'attempts' => 0,
                'reserved' => 0,
                'reserved_at' => null,
                'available_at' => 'available',
                'created_at' => 'created',
            ]], $records);
        });

        $queue->bulk(['foo', 'bar'], ['data'], 'queue');
    }
}
