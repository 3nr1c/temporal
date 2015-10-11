<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 Enric Florit
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author Enric Florit <enric@skibeta.com>
 * @since 0.1
 */

use Temporal\Temporal;

class TemporalTest extends PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        if (class_exists("\\Redis")) {
            $redis = new \Redis();
            $redis->connect("127.0.0.1", "6379");
            Temporal::setRedis($redis);
        } else {
            self::fail("Class Redis isn't installed");
        }
    }

    public function testConstructor() {
        $temporal = new Temporal("test_constructor");
        $this->assertEquals(0, $temporal->getInitialNumber());
        $this->assertEquals(0, $temporal->getCurrentNumber());
    }

    public function testConstructorPositiveNumber() {
        $temporal = new Temporal("test_positive_number", 10);
        $this->assertEquals(10, $temporal->getInitialNumber());
        $this->assertEquals(10, $temporal->getCurrentNumber());
    }

    public function testConstructorNegativeNumber() {
        $temporal = new Temporal("test_negative_number", -10);
        $this->assertEquals(-10, $temporal->getInitialNumber());
        $this->assertEquals(-10, $temporal->getCurrentNumber());
    }

    public function testRegisterDefaultValue() {
        $temporal = new Temporal("test_register_default", 10);
        $this->assertEquals(9, $temporal->register("test1"));
        $this->assertEquals(9, $temporal->getCurrentNumber());
    }

    public function testRegisterDefaultValueAndDelete() {
        $temporal = new Temporal("test_register_default2", 10);
        $this->assertEquals(9, $temporal->register("test1"));
        $this->assertEquals(9, $temporal->getCurrentNumber());
        $this->assertEquals(10, $temporal->delete("test1"));
        $this->assertEquals(10, $temporal->getCurrentNumber());
    }

    public function testRegisterValue() {
        $temporal = new Temporal("test_register_value", 10);
        $this->assertEquals(12, $temporal->register("test1", 2));
        $this->assertEquals(12, $temporal->getCurrentNumber());
        $this->assertEquals(9, $temporal->register("test2", -3));
        $this->assertEquals(9, $temporal->getCurrentNumber());
    }

    public function testTtl() {
        $temporal = new Temporal("test_ttl", 10);
        $this->assertEquals(9, $temporal->register("test1", -1, 1));
        $this->assertEquals(5, $temporal->register("test2", -4));
        $this->assertEquals(5, $temporal->getCurrentNumber());
        sleep(2);
        $this->assertEquals(6, $temporal->getCurrentNumber());
    }

    public function testReset() {
        $temporal = new Temporal("test_reset", 10);
        $this->assertEquals(8, $temporal->register("test1", -2));
        $this->assertEquals(4, $temporal->register("test2", -4));

        $this->assertEquals(4, $temporal->getCurrentNumber());

        $this->assertEquals(10, $temporal->reset());
        $this->assertEquals(10, $temporal->getCurrentNumber());

        $this->assertEquals(11, $temporal->register("test3", 1));
        $this->assertEquals(11, $temporal->getCurrentNumber());
    }

    public static function tearDownAfterClass() {
        if (class_exists("\\Redis")) {
            $redis = new \Redis();
            $redis->connect("127.0.0.1", "6379");

            $generatedKeys = $redis->keys("temporal::test_*");
            foreach ($generatedKeys as $key) {
                $redis->delete($key);
            }
        }
    }
}
