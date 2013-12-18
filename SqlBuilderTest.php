<?php
/**
 * Copyright 2013 Kimura Youichi <kim.upsilon@bucyou.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, version 2.0
 */

require_once 'SqlBuilder.php';

class SqlBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testSimpleAppend()
    {
        $sql = new Sql;
        $sql->append('LINE 1');
        $sql->append('LINE 2');
        $sql->append('LINE 3');

        $this->assertEquals("LINE 1\nLINE 2\nLINE 3", $sql->getSql());
        $this->assertCount(0, $sql->getArguments());
    }

    public function testSimpleArgs()
    {
        $sql = new Sql;
        $sql->append('arg @0 @1', ['a1', 'a2']);

        $this->assertEquals('arg @0 @1', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(2, $args);
        $this->assertEquals('a1', $args[0]);
        $this->assertEquals('a2', $args[1]);
    }

    public function testUnusedArgs()
    {
        $sql = new Sql;
        $sql->append('arg @0 @2', ['a1', 'a2', 'a3']);

        $this->assertEquals('arg @0 @1', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(2, $args);
        $this->assertEquals('a1', $args[0]);
        $this->assertEquals('a3', $args[1]);
    }

    public function testUnorderedArgs()
    {
        $sql = new Sql;
        $sql->append('arg @2 @1', ['a1', 'a2', 'a3']);

        $this->assertEquals('arg @0 @1', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(2, $args);
        $this->assertEquals('a3', $args[0]);
        $this->assertEquals('a2', $args[1]);
    }

    public function testRepeatedArgs()
    {
        $sql = new Sql;
        $sql->append('arg @0 @1 @0 @1', ['a1', 'a2']);

        $this->assertEquals('arg @0 @1 @2 @3', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(4, $args);
        $this->assertEquals('a1', $args[0]);
        $this->assertEquals('a2', $args[1]);
        $this->assertEquals('a1', $args[2]);
        $this->assertEquals('a2', $args[3]);
    }

    public function testMysqlUserVars()
    {
        $sql = new Sql;
        $sql->append('arg @@user1 @2 @1 @@@system1', ['a1', 'a2', 'a3']);

        $this->assertEquals('arg @@user1 @0 @1 @@@system1', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(2, $args);
        $this->assertEquals('a3', $args[0]);
        $this->assertEquals('a2', $args[1]);
    }

    public function testNamedArgs()
    {
        $sql = new Sql;
        $sql->append('arg @name @password', ['name' => 'n', 'password' => 'p']);

        $this->assertEquals('arg @0 @1', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(2, $args);
        $this->assertEquals('n', $args[0]);
        $this->assertEquals('p', $args[1]);
    }

    public function testMixedNamedAndNumberedArgs()
    {
        $sql = new Sql;
        $sql->append('arg @0 @name @1 @password @2', ['a1', 'a2', 'a3', 'name' => 'n', 'password' => 'p']);

        $this->assertEquals('arg @0 @1 @2 @3 @4', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(5, $args);
        $this->assertEquals('a1', $args[0]);
        $this->assertEquals('n', $args[1]);
        $this->assertEquals('a2', $args[2]);
        $this->assertEquals('p', $args[3]);
        $this->assertEquals('a3', $args[4]);
    }

    public function testAppendWithArgs()
    {
        $sql = new Sql;
        $sql->append('l1 @0', 'a0');
        $sql->append('l2 @0', 'a1');
        $sql->append('l3 @0', 'a2');

        $this->assertEquals("l1 @0\nl2 @1\nl3 @2", $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(3, $args);
        $this->assertEquals('a0', $args[0]);
        $this->assertEquals('a1', $args[1]);
        $this->assertEquals('a2', $args[2]);
    }

    public function testAppendWithArgs2()
    {
        $sql = new Sql;
        $sql->append('l1');
        $sql->append('l2 @0 @1', ['a1', 'a2']);
        $sql->append('l3 @0', ['a3']);

        $this->assertEquals("l1\nl2 @0 @1\nl3 @2", $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(3, $args);
        $this->assertEquals('a1', $args[0]);
        $this->assertEquals('a2', $args[1]);
        $this->assertEquals('a3', $args[2]);
    }

    public function testInvalidArgIndex()
    {
        $this->setExpectedException('OutOfRangeException');

        $sql = new Sql;
        $sql->append('arg @0 @1', ['a0']);
        $this->assertEquals('arg @0 @1', $sql->getSql());
    }

    public function testInvalidArgName()
    {
        $this->setExpectedException('InvalidArgumentException');

        $sql = new Sql;
        $sql->append('arg @name1 @name2', ['x' => 1, 'y' => 2]);
        $this->assertEquals('arg @0 @1', $sql->getSql());
    }

    public function testAppendInstances()
    {
        $sql = new Sql('l0 @0', ['a0']);
        $sql1 = new Sql('l1 @0', ['a1']);
        $sql2 = new Sql('l2 @0', ['a2']);

        $this->assertSame($sql->append($sql1), $sql);
        $this->assertSame($sql->append($sql2), $sql);

        $this->assertEquals("l0 @0\nl1 @1\nl2 @2", $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(3, $args);
        $this->assertEquals('a0', $args[0]);
        $this->assertEquals('a1', $args[1]);
        $this->assertEquals('a2', $args[2]);
    }

    public function testConsecutiveWhere()
    {
        $sql = new Sql();
        $sql->append('SELECT * FROM blah');

        $sql->append('WHERE x');
        $sql->append('WHERE y');

        $this->assertEquals("SELECT * FROM blah\nWHERE x\nAND y", $sql->getSql());
    }

    public function testConsecutiveOrderBy()
    {
        $sql = new Sql();
        $sql->append('SELECT * FROM blah');

        $sql->append('ORDER BY x');
        $sql->append('ORDER BY y');

        $this->assertEquals("SELECT * FROM blah\nORDER BY x\n, y", $sql->getSql());
    }

    public function testParamExpansion1()
    {
        $sql = Sql::builder()->append('@0 IN (@1) @2', [20, [1, 2, 3], 30]);

        $this->assertEquals('@0 IN (@1,@2,@3) @4', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(5, $args);
        $this->assertEquals(20, $args[0]);
        $this->assertEquals(1, $args[1]);
        $this->assertEquals(2, $args[2]);
        $this->assertEquals(3, $args[3]);
        $this->assertEquals(30, $args[4]);
    }

    public function testParamExpansion2()
    {
        // Out of order expansion
        $sql = Sql::builder()->append('IN (@3) (@1)', [null, [1, 2, 3], null, [4, 5, 6]]);

        $this->assertEquals('IN (@0,@1,@2) (@3,@4,@5)', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(6, $args);
        $this->assertEquals(4, $args[0]);
        $this->assertEquals(5, $args[1]);
        $this->assertEquals(6, $args[2]);
        $this->assertEquals(1, $args[3]);
        $this->assertEquals(2, $args[4]);
        $this->assertEquals(3, $args[5]);
    }

    public function testParamExpansionNamed()
    {
        // Expand a named parameter
        $sql = Sql::builder()->append('IN (@numbers)', ['numbers' => [1, 2, 3]]);

        $this->assertEquals('IN (@0,@1,@2)', $sql->getSql());

        $args = $sql->getArguments();
        $this->assertCount(3, $args);
        $this->assertEquals(1, $args[0]);
        $this->assertEquals(2, $args[1]);
        $this->assertEquals(3, $args[2]);
    }

    public function testJoin()
    {
        $sql = Sql::builder()
            ->select('*')
            ->from('articles')
            ->leftJoin('comments')->on('articles.article_id=comments.article_id');

        $this->assertEquals("SELECT *\nFROM articles\nLEFT JOIN comments\nON articles.article_id=comments.article_id", $sql->getSql());
    }
}
// vim: et ts=4 sw=4 sts=4 fenc=utf-8
