<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

class RoutingRouteTest extends PHPUnit_Framework_TestCase
{
    public function testBasicDispatchingOfRoutes()
    {
        $router = $this->getRouter();
        $router->get('foo/bar', function () { return 'hello'; });
        $this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/bar', function () { throw new Illuminate\Http\Exception\HttpResponseException(new Response('hello')); });
        $this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

        $router = $this->getRouter();
        $route = $router->get('foo/bar', ['domain' => 'api.{name}.bar', function ($name) { return $name; }]);
        $route = $router->get('foo/bar', ['domain' => 'api.{name}.baz', function ($name) { return $name; }]);
        $this->assertEquals('taylor', $router->dispatch(Request::create('http://api.taylor.bar/foo/bar', 'GET'))->getContent());
        $this->assertEquals('dayle', $router->dispatch(Request::create('http://api.dayle.baz/foo/bar', 'GET'))->getContent());

        $router = $this->getRouter();
        $route = $router->get('foo/{age}', ['domain' => 'api.{name}.bar', function ($name, $age) { return $name.$age; }]);
        $this->assertEquals('taylor25', $router->dispatch(Request::create('http://api.taylor.bar/foo/25', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/bar', function () { return 'hello'; });
        $router->post('foo/bar', function () { return 'post hello'; });
        $this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
        $this->assertEquals('post hello', $router->dispatch(Request::create('foo/bar', 'POST'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $this->assertEquals('taylor', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/{bar}/{baz?}', function ($name, $age = 25) { return $name.$age; });
        $this->assertEquals('taylor25', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/{name}/boom/{age?}/{location?}', function ($name, $age = 25, $location = 'AR') { return $name.$age.$location; });
        $this->assertEquals('taylor30AR', $router->dispatch(Request::create('foo/taylor/boom/30', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('{bar}/{baz?}', function ($name, $age = 25) { return $name.$age; });
        $this->assertEquals('taylor25', $router->dispatch(Request::create('taylor', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('{baz?}', function ($age = 25) { return $age; });
        $this->assertEquals('25', $router->dispatch(Request::create('/', 'GET'))->getContent());
        $this->assertEquals('30', $router->dispatch(Request::create('30', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('{foo?}/{baz?}', ['as' => 'foo', function ($name = 'taylor', $age = 25) { return $name.$age; }]);
        $this->assertEquals('taylor25', $router->dispatch(Request::create('/', 'GET'))->getContent());
        $this->assertEquals('fred25', $router->dispatch(Request::create('fred', 'GET'))->getContent());
        $this->assertEquals('fred30', $router->dispatch(Request::create('fred/30', 'GET'))->getContent());
        $this->assertTrue($router->currentRouteNamed('foo'));
        $this->assertTrue($router->is('foo'));
        $this->assertFalse($router->is('bar'));

        $router = $this->getRouter();
        $router->get('foo/bar', function () { return 'hello'; });
        $this->assertEquals('', $router->dispatch(Request::create('foo/bar', 'HEAD'))->getContent());

        $router = $this->getRouter();
        $router->any('foo/bar', function () { return 'hello'; });
        $this->assertEquals('', $router->dispatch(Request::create('foo/bar', 'HEAD'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/bar', function () { return 'first'; });
        $router->get('foo/bar', function () { return 'second'; });
        $this->assertEquals('second', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/bar/åαф', function () { return 'hello'; });
        $this->assertEquals('hello', $router->dispatch(Request::create('foo/bar/%C3%A5%CE%B1%D1%84', 'GET'))->getContent());

        $router = $this->getRouter();
        $router->get('foo/bar', ['boom' => 'auth', function () { return 'closure'; }]);
        $this->assertEquals('closure', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
    }

    public function testMacro()
    {
        $router = $this->getRouter();
        $router->macro('webhook', function () use ($router) {
            $router->match(['GET', 'POST'], 'webhook', function () { return 'OK'; });
        });
        $router->webhook();
        $this->assertEquals('OK', $router->dispatch(Request::create('webhook', 'GET'))->getContent());
        $this->assertEquals('OK', $router->dispatch(Request::create('webhook', 'POST'))->getContent());
    }

    public function testClassesCanBeInjectedIntoRoutes()
    {
        unset($_SERVER['__test.route_inject']);
        $router = $this->getRouter();
        $router->get('foo/{var}', function (stdClass $foo, $var) {
            $_SERVER['__test.route_inject'] = func_get_args();

            return 'hello';
        });

        $this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
        $this->assertInstanceOf('stdClass', $_SERVER['__test.route_inject'][0]);
        $this->assertEquals('bar', $_SERVER['__test.route_inject'][1]);

        unset($_SERVER['__test.route_inject']);
    }

    public function testOptionsResponsesAreGeneratedByDefault()
    {
        $router = $this->getRouter();
        $router->get('foo/bar', function () { return 'hello'; });
        $router->post('foo/bar', function () { return 'hello'; });
        $response = $router->dispatch(Request::create('foo/bar', 'OPTIONS'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('GET,HEAD,POST', $response->headers->get('Allow'));
    }

    public function testHeadDispatcher()
    {
        $router = $this->getRouter();
        $router->match(['GET', 'POST'], 'foo', function () { return 'bar'; });

        $response = $router->dispatch(Request::create('foo', 'OPTIONS'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('GET,HEAD,POST', $response->headers->get('Allow'));

        $response = $router->dispatch(Request::create('foo', 'HEAD'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());

        $router = $this->getRouter();
        $router->match(['GET'], 'foo', function () { return 'bar'; });

        $response = $router->dispatch(Request::create('foo', 'OPTIONS'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('GET,HEAD', $response->headers->get('Allow'));

        $router = $this->getRouter();
        $router->match(['POST'], 'foo', function () { return 'bar'; });

        $response = $router->dispatch(Request::create('foo', 'OPTIONS'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('POST', $response->headers->get('Allow'));
    }

    public function testNonGreedyMatches()
    {
        $route = new Route('GET', 'images/{id}.{ext}', function () {});

        $request1 = Request::create('images/1.png', 'GET');
        $this->assertTrue($route->matches($request1));
        $route->bind($request1);
        $this->assertTrue($route->hasParameter('id'));
        $this->assertFalse($route->hasParameter('foo'));
        $this->assertEquals('1', $route->parameter('id'));
        $this->assertEquals('png', $route->parameter('ext'));

        $request2 = Request::create('images/12.png', 'GET');
        $this->assertTrue($route->matches($request2));
        $route->bind($request2);
        $this->assertEquals('12', $route->parameter('id'));
        $this->assertEquals('png', $route->parameter('ext'));

        // Test parameter() default value
        $route = new Route('GET', 'foo/{foo?}', function () {});

        $request3 = Request::create('foo', 'GET');
        $this->assertTrue($route->matches($request3));
        $route->bind($request3);
        $this->assertEquals('bar', $route->parameter('foo', 'bar'));
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testRoutesDontMatchNonMatchingPathsWithLeadingOptionals()
    {
        $router = $this->getRouter();
        $router->get('{baz?}', function ($age = 25) { return $age; });
        $this->assertEquals('25', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testRoutesDontMatchNonMatchingDomain()
    {
        $router = $this->getRouter();
        $route = $router->get('foo/bar', ['domain' => 'api.foo.bar', function () { return 'hello'; }]);
        $this->assertEquals('hello', $router->dispatch(Request::create('http://api.baz.boom/foo/bar', 'GET'))->getContent());
    }

    public function testMatchesMethodAgainstRequests()
    {
        /*
         * Basic
         */
        $request = Request::create('foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', function () {});
        $this->assertTrue($route->matches($request));

        $request = Request::create('foo/bar', 'GET');
        $route = new Route('GET', 'foo', function () {});
        $this->assertFalse($route->matches($request));

        /*
         * Method checks
         */
        $request = Request::create('foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', function () {});
        $this->assertTrue($route->matches($request));

        $request = Request::create('foo/bar', 'POST');
        $route = new Route('GET', 'foo', function () {});
        $this->assertFalse($route->matches($request));

        /*
         * Domain checks
         */
        $request = Request::create('http://something.foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['domain' => '{foo}.foo.com', function () {}]);
        $this->assertTrue($route->matches($request));

        $request = Request::create('http://something.bar.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['domain' => '{foo}.foo.com', function () {}]);
        $this->assertFalse($route->matches($request));

        /*
         * HTTPS checks
         */
        $request = Request::create('https://foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['https', function () {}]);
        $this->assertTrue($route->matches($request));

        $request = Request::create('https://foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['https', 'baz' => true, function () {}]);
        $this->assertTrue($route->matches($request));

        $request = Request::create('http://foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['https', function () {}]);
        $this->assertFalse($route->matches($request));

        /*
         * HTTP checks
         */
        $request = Request::create('https://foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['http', function () {}]);
        $this->assertFalse($route->matches($request));

        $request = Request::create('http://foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['http', function () {}]);
        $this->assertTrue($route->matches($request));

        $request = Request::create('http://foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['baz' => true, function () {}]);
        $this->assertTrue($route->matches($request));
    }

    public function testWherePatternsProperlyFilter()
    {
        $request = Request::create('foo/123', 'GET');
        $route = new Route('GET', 'foo/{bar}', function () {});
        $route->where('bar', '[0-9]+');
        $this->assertTrue($route->matches($request));

        $request = Request::create('foo/123abc', 'GET');
        $route = new Route('GET', 'foo/{bar}', function () {});
        $route->where('bar', '[0-9]+');
        $this->assertFalse($route->matches($request));

        $request = Request::create('foo/123abc', 'GET');
        $route = new Route('GET', 'foo/{bar}', ['where' => ['bar' => '[0-9]+'], function () {}]);
        $route->where('bar', '[0-9]+');
        $this->assertFalse($route->matches($request));

        /*
         * Optional
         */
        $request = Request::create('foo/123', 'GET');
        $route = new Route('GET', 'foo/{bar?}', function () {});
        $route->where('bar', '[0-9]+');
        $this->assertTrue($route->matches($request));

        $request = Request::create('foo/123', 'GET');
        $route = new Route('GET', 'foo/{bar?}', ['where' => ['bar' => '[0-9]+'], function () {}]);
        $route->where('bar', '[0-9]+');
        $this->assertTrue($route->matches($request));

        $request = Request::create('foo/123', 'GET');
        $route = new Route('GET', 'foo/{bar?}/{baz?}', function () {});
        $route->where('bar', '[0-9]+');
        $this->assertTrue($route->matches($request));

        $request = Request::create('foo/123/foo', 'GET');
        $route = new Route('GET', 'foo/{bar?}/{baz?}', function () {});
        $route->where('bar', '[0-9]+');
        $this->assertTrue($route->matches($request));

        $request = Request::create('foo/123abc', 'GET');
        $route = new Route('GET', 'foo/{bar?}', function () {});
        $route->where('bar', '[0-9]+');
        $this->assertFalse($route->matches($request));
    }

    public function testDotDoesNotMatchEverything()
    {
        $route = new Route('GET', 'images/{id}.{ext}', function () {});

        $request1 = Request::create('images/1.png', 'GET');
        $this->assertTrue($route->matches($request1));
        $route->bind($request1);
        $this->assertEquals('1', $route->parameter('id'));
        $this->assertEquals('png', $route->parameter('ext'));

        $request2 = Request::create('images/12.png', 'GET');
        $this->assertTrue($route->matches($request2));
        $route->bind($request2);
        $this->assertEquals('12', $route->parameter('id'));
        $this->assertEquals('png', $route->parameter('ext'));
    }

    public function testRouteBinding()
    {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $router->bind('bar', function ($value) { return strtoupper($value); });
        $this->assertEquals('TAYLOR', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());
    }

    public function testRouteClassBinding()
    {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $router->bind('bar', 'RouteBindingStub');
        $this->assertEquals('TAYLOR', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());
    }

    public function testRouteClassMethodBinding()
    {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $router->bind('bar', 'RouteBindingStub@find');
        $this->assertEquals('dragon', $router->dispatch(Request::create('foo/Dragon', 'GET'))->getContent());
    }

    public function testModelBinding()
    {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $router->model('bar', 'RouteModelBindingStub');
        $this->assertEquals('TAYLOR', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testModelBindingWithNullReturn()
    {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $router->model('bar', 'RouteModelBindingNullStub');
        $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent();
    }

    public function testModelBindingWithCustomNullReturn()
    {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $router->model('bar', 'RouteModelBindingNullStub', function () { return 'missing'; });
        $this->assertEquals('missing', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());
    }

    public function testModelBindingWithBindingClosure()
    {
        $router = $this->getRouter();
        $router->get('foo/{bar}', function ($name) { return $name; });
        $router->model('bar', 'RouteModelBindingNullStub', function ($value) { return (new RouteModelBindingClosureStub())->findAlternate($value); });
        $this->assertEquals('tayloralt', $router->dispatch(Request::create('foo/TAYLOR', 'GET'))->getContent());
    }

    public function testGroupMerging()
    {
        $old = ['prefix' => 'foo/bar/'];
        $this->assertEquals(['prefix' => 'foo/bar/baz', 'namespace' => null, 'where' => []], Router::mergeGroup(['prefix' => 'baz'], $old));

        $old = ['domain' => 'foo'];
        $this->assertEquals(['domain' => 'baz', 'prefix' => null, 'namespace' => null, 'where' => []], Router::mergeGroup(['domain' => 'baz'], $old));

        $old = ['as' => 'foo.'];
        $this->assertEquals(['as' => 'foo.bar', 'prefix' => null, 'namespace' => null, 'where' => []], Router::mergeGroup(['as' => 'bar'], $old));

        $old = ['where' => ['var1' => 'foo', 'var2' => 'bar']];
        $this->assertEquals(['prefix' => null, 'namespace' => null, 'where' => [
            'var1' => 'foo', 'var2' => 'baz', 'var3' => 'qux',
        ]], Router::mergeGroup(['where' => ['var2' => 'baz', 'var3' => 'qux']], $old));

        $old = [];
        $this->assertEquals(['prefix' => null, 'namespace' => null, 'where' => [
            'var1' => 'foo', 'var2' => 'bar',
        ]], Router::mergeGroup(['where' => ['var1' => 'foo', 'var2' => 'bar']], $old));
    }

    public function testRouteGrouping()
    {
        /*
         * getPrefix() method
         */
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo'], function () use ($router) {
            $router->get('bar', function () { return 'hello'; });
        });
        $routes = $router->getRoutes();
        $routes = $routes->getRoutes();
        $this->assertEquals('foo', $routes[0]->getPrefix());
    }

    public function testRouteGroupingWithAs()
    {
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
            $router->get('bar', ['as' => 'bar', function () { return 'hello'; }]);
        });
        $routes = $router->getRoutes();
        $route = $routes->getByName('Foo::bar');
        $this->assertEquals('foo/bar', $route->getPath());
    }

    public function testNestedRouteGroupingWithAs()
    {
        /*
         * nested with all layers present
         */
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
            $router->group(['prefix' => 'bar', 'as' => 'Bar::'], function () use ($router) {
                $router->get('baz', ['as' => 'baz', function () { return 'hello'; }]);
            });
        });
        $routes = $router->getRoutes();
        $route = $routes->getByName('Foo::Bar::baz');
        $this->assertEquals('foo/bar/baz', $route->getPath());

        /*
         * nested with layer skipped
         */
        $router = $this->getRouter();
        $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
            $router->group(['prefix' => 'bar'], function () use ($router) {
                $router->get('baz', ['as' => 'baz', function () { return 'hello'; }]);
            });
        });
        $routes = $router->getRoutes();
        $route = $routes->getByName('Foo::baz');
        $this->assertEquals('foo/bar/baz', $route->getPath());
    }

    public function testRoutePrefixing()
    {
        /**
         * Prefix route
         */
        $router = $this->getRouter();
        $router->get('foo/bar', function() { return 'hello'; });
        $routes = $router->getRoutes();
        $routes = $routes->getRoutes();
        $routes[0]->prefix('prefix');
        $this->assertEquals('prefix/foo/bar', $routes[0]->uri());

        /**
         * Use empty prefix
         */
        $router = $this->getRouter();
        $router->get('foo/bar', function() { return 'hello'; });
        $routes = $router->getRoutes();
        $routes = $routes->getRoutes();
        $routes[0]->prefix('/');
        $this->assertEquals('foo/bar', $routes[0]->uri());

        /**
         * Prefix homepage
         */
        $router = $this->getRouter();
        $router->get('/', function() { return 'hello'; });
        $routes = $router->getRoutes();
        $routes = $routes->getRoutes();
        $routes[0]->prefix('prefix');
        $this->assertEquals('prefix', $routes[0]->uri());
    }

    public function testMergingControllerUses()
    {
        $router = $this->getRouter();
        $router->group(['namespace' => 'Namespace'], function () use ($router) {
            $router->get('foo/bar', 'Controller@action');
        });
        $routes = $router->getRoutes()->getRoutes();
        $action = $routes[0]->getAction();

        $this->assertEquals('Namespace\\Controller@action', $action['controller']);

        $router = $this->getRouter();
        $router->group(['namespace' => 'Namespace'], function () use ($router) {
            $router->group(['namespace' => 'Nested'], function () use ($router) {
                $router->get('foo/bar', 'Controller@action');
            });
        });
        $routes = $router->getRoutes()->getRoutes();
        $action = $routes[0]->getAction();

        $this->assertEquals('Namespace\\Nested\\Controller@action', $action['controller']);

        $router = $this->getRouter();
        $router->group(['prefix' => 'baz'], function () use ($router) {
            $router->group(['namespace' => 'Namespace'], function () use ($router) {
                $router->get('foo/bar', 'Controller@action');
            });
        });
        $routes = $router->getRoutes()->getRoutes();
        $action = $routes[0]->getAction();

        $this->assertEquals('Namespace\\Controller@action', $action['controller']);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidActionException()
    {
        $router = $this->getRouter();
        $router->get('/', ['uses' => 'Controller']);
    }

    public function testResourceRouting()
    {
        $router = $this->getRouter();
        $router->resource('foo', 'FooController');
        $routes = $router->getRoutes();
        $this->assertCount(8, $routes);

        $router = $this->getRouter();
        $router->resource('foo', 'FooController', ['only' => ['show', 'destroy']]);
        $routes = $router->getRoutes();

        $this->assertCount(2, $routes);

        $router = $this->getRouter();
        $router->resource('foo', 'FooController', ['except' => ['show', 'destroy']]);
        $routes = $router->getRoutes();

        $this->assertCount(6, $routes);

        $router = $this->getRouter();
        $router->resource('foo-bars', 'FooController', ['only' => ['show']]);
        $routes = $router->getRoutes();
        $routes = $routes->getRoutes();

        $this->assertEquals('foo-bars/{foo_bars}', $routes[0]->getUri());

        $router = $this->getRouter();
        $router->resource('foo-bars.foo-bazs', 'FooController', ['only' => ['show']]);
        $routes = $router->getRoutes();
        $routes = $routes->getRoutes();

        $this->assertEquals('foo-bars/{foo_bars}/foo-bazs/{foo_bazs}', $routes[0]->getUri());

        $router = $this->getRouter();
        $router->resource('foo-bars', 'FooController', ['only' => ['show'], 'as' => 'prefix']);
        $routes = $router->getRoutes();
        $routes = $routes->getRoutes();

        $this->assertEquals('foo-bars/{foo_bars}', $routes[0]->getUri());
        $this->assertEquals('prefix.foo-bars.show', $routes[0]->getName());
    }

    public function testResourceRouteNaming()
    {
        $router = $this->getRouter();
        $router->resource('foo', 'FooController');

        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.index'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.show'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.create'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.store'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.edit'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.update'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.destroy'));

        $router = $this->getRouter();
        $router->resource('foo.bar', 'FooController');

        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.index'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.show'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.create'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.store'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.edit'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.update'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.destroy'));

        $router = $this->getRouter();
        $router->resource('foo', 'FooController', ['names' => [
            'index' => 'foo',
            'show' => 'bar',
        ]]);

        $this->assertTrue($router->getRoutes()->hasNamedRoute('foo'));
        $this->assertTrue($router->getRoutes()->hasNamedRoute('bar'));
    }

    public function testRouterFiresRoutedEvent()
    {
        $events = new Illuminate\Events\Dispatcher();
        $router = new Router($events);
        $router->get('foo/bar', function () { return ''; });

        $request = Request::create('http://foo.com/foo/bar', 'GET');
        $route = new Route('GET', 'foo/bar', ['http', function () {}]);

        $_SERVER['__router.request'] = null;
        $_SERVER['__router.route'] = null;

        $router->matched(function ($route, $request) {
            $_SERVER['__router.request'] = $request;
            $_SERVER['__router.route'] = $route;
        });

        $router->dispatchToRoute($request);

        $this->assertInstanceOf('Illuminate\Http\Request', $_SERVER['__router.request']);
        $this->assertEquals($_SERVER['__router.request'], $request);
        unset($_SERVER['__router.request']);

        $this->assertInstanceOf('Illuminate\Routing\Route', $_SERVER['__router.route']);
        $this->assertEquals($_SERVER['__router.route']->getUri(), $route->getUri());
        unset($_SERVER['__router.route']);
    }

    public function testRouterPatternSetting()
    {
        $router = $this->getRouter();
        $router->pattern('test', 'pattern');
        $this->assertEquals(['test' => 'pattern'], $router->getPatterns());

        $router = $this->getRouter();
        $router->patterns(['test' => 'pattern', 'test2' => 'pattern2']);
        $this->assertEquals(['test' => 'pattern', 'test2' => 'pattern2'], $router->getPatterns());
    }

    public function testControllerRouting()
    {
        unset(
            $_SERVER['route.test.controller.middleware'], $_SERVER['route.test.controller.except.middleware'],
            $_SERVER['route.test.controller.middleware.class'],
            $_SERVER['route.test.controller.middleware.parameters.one'], $_SERVER['route.test.controller.middleware.parameters.two']
        );

        $router = new Router(new Illuminate\Events\Dispatcher, $container = new Illuminate\Container\Container);

        $container->singleton('illuminate.route.dispatcher', function ($container) use ($router) {
            return new Illuminate\Routing\ControllerDispatcher($router, $container);
        });

        $router->get('foo/bar', 'RouteTestControllerStub@index');

        $this->assertEquals('Hello World', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
        $this->assertTrue($_SERVER['route.test.controller.middleware']);
        $this->assertEquals('Illuminate\Http\Response', $_SERVER['route.test.controller.middleware.class']);
        $this->assertEquals(0, $_SERVER['route.test.controller.middleware.parameters.one']);
        $this->assertEquals(['foo', 'bar'], $_SERVER['route.test.controller.middleware.parameters.two']);
        $this->assertFalse(isset($_SERVER['route.test.controller.except.middleware']));
    }

    public function testControllerInspection()
    {
        $router = $this->getRouter();
        $router->controller('home', 'RouteTestInspectedControllerStub');
        $this->assertEquals('hello', $router->dispatch(Request::create('home/foo', 'GET'))->getContent());
    }

    protected function getRouter()
    {
        return new Router(new Illuminate\Events\Dispatcher);
    }
}

class RouteTestControllerStub extends Illuminate\Routing\Controller
{
    public function __construct()
    {
        $this->middleware('RouteTestControllerMiddleware');
        $this->middleware('RouteTestControllerParameterizedMiddlewareOne:0');
        $this->middleware('RouteTestControllerParameterizedMiddlewareTwo:foo,bar');
        $this->middleware('RouteTestControllerExceptMiddleware', ['except' => 'index']);
    }
    public function index()
    {
        return 'Hello World';
    }
}

class RouteTestControllerMiddleware
{
    public function handle($request, $next)
    {
        $_SERVER['route.test.controller.middleware'] = true;
        $response = $next($request);
        $_SERVER['route.test.controller.middleware.class'] = get_class($response);

        return $response;
    }
}

class RouteTestControllerParameterizedMiddlewareOne
{
    public function handle($request, $next, $parameter)
    {
        $_SERVER['route.test.controller.middleware.parameters.one'] = $parameter;

        return $next($request);
    }
}

class RouteTestControllerParameterizedMiddlewareTwo
{
    public function handle($request, $next, $parameter1, $parameter2)
    {
        $_SERVER['route.test.controller.middleware.parameters.two'] = [$parameter1, $parameter2];

        return $next($request);
    }
}

class RouteTestInspectedControllerStub extends Illuminate\Routing\Controller
{
    public function getFoo()
    {
        return 'hello';
    }
}

class RouteTestControllerExceptMiddleware
{
    public function handle($request, $next)
    {
        $_SERVER['route.test.controller.except.middleware'] = true;

        return $next($request);
    }
}

class RouteBindingStub
{
    public function bind($value, $route)
    {
        return strtoupper($value);
    }
    public function find($value, $route)
    {
        return strtolower($value);
    }
}

class RouteModelBindingStub
{
    public function getRouteKeyName()
    {
        return 'id';
    }
    public function where($key, $value)
    {
        $this->value = $value;

        return $this;
    }
    public function first()
    {
        return strtoupper($this->value);
    }
}

class RouteModelBindingNullStub
{
    public function getRouteKeyName()
    {
        return 'id';
    }
    public function where($key, $value)
    {
        return $this;
    }
    public function first()
    {
    }
}

class RouteModelBindingClosureStub
{
    public function findAlternate($value)
    {
        return strtolower($value).'alt';
    }
}
