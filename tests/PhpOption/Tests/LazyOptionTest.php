<?php

namespace PhpOption\Tests;

use stdClass;
use ArrayIterator;
use PhpOption\Some;
use PhpOption\None;
use PhpOption\LazyOption;

class LazyOptionTest extends \PHPUnit_Framework_TestCase
{
    private $subject;

    public function setUp()
    {
        $this->subject = $this
            ->getMockBuilder('Subject')
            ->setMethods(array('execute'))
            ->getMock();
    }

    public function testGetWithArgumentsAndConstructor()
    {
        $some = LazyOption::create(array($this->subject, 'execute'), array('foo'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->with('foo')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertEquals('foo', $some->get());
        $this->assertEquals('foo', $some->getOrElse(null));
        $this->assertEquals('foo', $some->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $some->getOrThrow(new \RuntimeException('does_not_exist')));
        $this->assertFalse($some->isEmpty());
    }

    public function testGetWithArgumentsAndCreate()
    {
        $some = new LazyOption(array($this->subject, 'execute'), array('foo'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->with('foo')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertEquals('foo', $some->get());
        $this->assertEquals('foo', $some->getOrElse(null));
        $this->assertEquals('foo', $some->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $some->getOrThrow(new \RuntimeException('does_not_exist')));
        $this->assertFalse($some->isEmpty());
    }

    public function testGetWithoutArgumentsAndConstructor()
    {
        $some = new LazyOption(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertEquals('foo', $some->get());
        $this->assertEquals('foo', $some->getOrElse(null));
        $this->assertEquals('foo', $some->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $some->getOrThrow(new \RuntimeException('does_not_exist')));
        $this->assertFalse($some->isEmpty());
    }

    public function testGetWithoutArgumentsAndCreate()
    {
        $option = LazyOption::create(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(Some::create('foo')));

        $this->assertTrue($option->isDefined());
        $this->assertFalse($option->isEmpty());
        $this->assertEquals('foo', $option->get());
        $this->assertEquals('foo', $option->getOrElse(null));
        $this->assertEquals('foo', $option->getOrCall('does_not_exist'));
        $this->assertEquals('foo', $option->getOrThrow(new \RuntimeException('does_not_exist')));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage None has no value
     */
    public function testCallbackReturnsNull()
    {
        $option = LazyOption::create(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(None::create()));

        $this->assertFalse($option->isDefined());
        $this->assertTrue($option->isEmpty());
        $this->assertEquals('alt', $option->getOrElse('alt'));
        $this->assertEquals('alt', $option->getOrCall(function(){return 'alt';}));

        $option->get();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Expected instance of \PhpOption\Option
     */
    public function testExceptionIsThrownIfCallbackReturnsNonOption()
    {
        $option = LazyOption::create(array($this->subject, 'execute'));

        $this->subject
            ->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(null));

        $this->assertFalse($option->isDefined());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid callback given
     */
    public function testInvalidCallbackAndConstructor()
    {
        new LazyOption('invalidCallback');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid callback given
     */
    public function testInvalidCallbackAndCreate()
    {
        LazyOption::create('invalidCallback');
    }

    public function testifDefined()
    {
        $called = false;
        $self = $this;
        $this->assertNull(LazyOption::fromValue('foo')->ifDefined(function($v) use (&$called, $self) {
            $called = true;
            $self->assertEquals('foo', $v);
        }));
        $this->assertTrue($called);
    }

    public function testForAll()
    {
        $called = false;
        $self = $this;
        $this->assertInstanceOf('PhpOption\Some', LazyOption::fromValue('foo')->forAll(function($v) use (&$called, $self) {
            $called = true;
            $self->assertEquals('foo', $v);
        }));
        $this->assertTrue($called);
    }

    public function testOrElse()
    {
        $some = Some::create('foo');
        $lazy = LazyOption::create(function() use ($some) {return $some;});
        $this->assertSame($some, $lazy->orElse(None::create()));
        $this->assertSame($some, $lazy->orElse(Some::create('bar')));
    }

    public function testFoldLeftRight()
    {
        $callback = function() { };

        $option = $this->getMockForAbstractClass('PhpOption\Option');
        $option->expects($this->once())
            ->method('foldLeft')
            ->with(5, $callback)
            ->will($this->returnValue(6));
        $lazyOption = new LazyOption(function() use ($option) { return $option; });
        $this->assertSame(6, $lazyOption->foldLeft(5, $callback));

        $option->expects($this->once())
            ->method('foldRight')
            ->with(5, $callback)
            ->will($this->returnValue(6));
        $lazyOption = new LazyOption(function() use ($option) { return $option; });
        $this->assertSame(6, $lazyOption->foldRight(5, $callback));
    }

    public function testFilterIsOneOf()
    {
        $some     = new Some(new stdClass());
        $lazy_opt = LazyOption::create(function() use ($some) { return $some; });

        $this->assertInstanceOf('PhpOption\None', $lazy_opt->filterIsOneOf('unknown', 'unknown2'));
        $this->assertInstanceOf('PhpOption\None', $lazy_opt->filterIsOneOf(['unknown', 'unknown2']));

        $this->assertSame($some, $lazy_opt->filterIsOneOf(stdClass::class, 'unknown'));
        $this->assertSame($some, $lazy_opt->filterIsOneOf([stdClass::class, 'unknown']));
        $this->assertSame($some, $lazy_opt->filterIsOneOf(new ArrayIterator([stdClass::class, 'unknown'])));
    }
}
