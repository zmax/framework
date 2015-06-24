<?php

use Illuminate\Console\Parser;

class ConsoleParserTest extends PHPUnit_Framework_TestCase
{
    public function testBasicParameterParsing()
    {
        $results = Parser::parse('command:name');

        $this->assertEquals('command:name', $results[0]);

        $results = Parser::parse('command:name {argument} {--option}');

        $this->assertEquals('command:name', $results[0]);
        $this->assertEquals('argument', $results[1][0]->getName());
        $this->assertEquals('option', $results[2][0]->getName());
        $this->assertFalse($results[2][0]->acceptValue());

        $results = Parser::parse('command:name {argument*} {--option=}');

        $this->assertEquals('command:name', $results[0]);
        $this->assertEquals('argument', $results[1][0]->getName());
        $this->assertTrue($results[1][0]->isArray());
        $this->assertTrue($results[1][0]->isRequired());
        $this->assertEquals('option', $results[2][0]->getName());
        $this->assertTrue($results[2][0]->acceptValue());

        $results = Parser::parse('command:name {argument?*} {--option=*}');

        $this->assertEquals('command:name', $results[0]);
        $this->assertEquals('argument', $results[1][0]->getName());
        $this->assertTrue($results[1][0]->isArray());
        $this->assertFalse($results[1][0]->isRequired());
        $this->assertEquals('option', $results[2][0]->getName());
        $this->assertTrue($results[2][0]->acceptValue());
        $this->assertTrue($results[2][0]->isArray());

        $results = Parser::parse('command:name {argument?* : The argument description.}    {--option=* : The option description.}');

        $this->assertEquals('command:name', $results[0]);
        $this->assertEquals('argument', $results[1][0]->getName());
        $this->assertEquals('The argument description.', $results[1][0]->getDescription());
        $this->assertTrue($results[1][0]->isArray());
        $this->assertFalse($results[1][0]->isRequired());
        $this->assertEquals('option', $results[2][0]->getName());
        $this->assertEquals('The option description.', $results[2][0]->getDescription());
        $this->assertTrue($results[2][0]->acceptValue());
        $this->assertTrue($results[2][0]->isArray());

        $results = Parser::parse('command:name
            {argument?* : The argument description.}
            {--option=* : The option description.}');

        $this->assertEquals('command:name', $results[0]);
        $this->assertEquals('argument', $results[1][0]->getName());
        $this->assertEquals('The argument description.', $results[1][0]->getDescription());
        $this->assertTrue($results[1][0]->isArray());
        $this->assertFalse($results[1][0]->isRequired());
        $this->assertEquals('option', $results[2][0]->getName());
        $this->assertEquals('The option description.', $results[2][0]->getDescription());
        $this->assertTrue($results[2][0]->acceptValue());
        $this->assertTrue($results[2][0]->isArray());
    }
}
