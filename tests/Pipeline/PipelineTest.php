<?php

use Illuminate\Pipeline\Pipeline;

class PipelineTest extends PHPUnit_Framework_TestCase
{
    public function testPipelineBasicUsage()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            return $next($piped);
        };

        $result = (new Pipeline(new Illuminate\Container\Container))
                    ->send('foo')
                    ->through(['PipelineTestPipeOne', $pipeTwo])
                    ->then(function ($piped) {
                        return $piped;
                    });

        $this->assertEquals('foo', $result);
        $this->assertEquals('foo', $_SERVER['__test.pipe.one']);
        $this->assertEquals('foo', $_SERVER['__test.pipe.two']);

        unset($_SERVER['__test.pipe.one']);
        unset($_SERVER['__test.pipe.two']);
    }

    public function testPipelineUsageWithParameters()
    {
        $parameters = ['one','two'];

        $result = (new Pipeline(new Illuminate\Container\Container))
            ->send('foo')
            ->through('PipelineTestParameterPipe:'.implode(',', $parameters))
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertEquals('foo', $result);
        $this->assertEquals($parameters, $_SERVER['__test.pipe.parameters']);

        unset($_SERVER['__test.pipe.parameters']);
    }
}

class PipelineTestPipeOne
{
    public function handle($piped, $next)
    {
        $_SERVER['__test.pipe.one'] = $piped;

        return $next($piped);
    }
}

class PipelineTestParameterPipe
{
    public function handle($piped, $next, $parameter1 = null, $parameter2 = null)
    {
        $_SERVER['__test.pipe.parameters'] = [$parameter1, $parameter2];

        return $next($piped);
    }
}
