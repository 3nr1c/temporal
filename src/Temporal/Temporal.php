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

namespace Temporal;

use Assert\Assertion;

class Temporal {
    /**
     * @var string
     */
    private $identifier;
    /**
     * @var int
     */
    private $initialNumber;

    /**
     * @var \Redis
     */
    private static $redis;

    /**
     * @param string $identifier
     * @param int $initialNumber
     * @param \Redis|null $redis
     * @throws \Exception
     */
    public function __construct($identifier, $initialNumber = 0, \Redis $redis = null) {
        Assertion::string($identifier, "Temporal identifier must be a string");
        Assertion::integer($initialNumber, "Temporal initial number must be an integer");

        $this->identifier = "temporal::" . $identifier;
        $this->initialNumber = $initialNumber;

        if (!is_null($redis)) self::$redis = $redis;
    }

    /**
     * @param \Redis $redis
     */
    public static function setRedis(\Redis $redis) {
        self::$redis = $redis;
    }

    /**
     * @return int
     */
    public function getInitialNumber() {
        return $this->initialNumber;
    }

    /**
     * @return int
     */
    public function getCurrentNumber() {
        Assertion::notNull(self::$redis, "Redis connection hasn't been set");

        $initialNumber = $this->initialNumber;

        $registeredKeys = self::$redis->sMembers($this->identifier);

        foreach ($registeredKeys as $key) {
            $value = self::$redis->get($key);
            if ($value !== false) {
                $initialNumber += $value;
            } else {
                self::$redis->sRemove($this->identifier, $key);
            }
        }

        return $initialNumber;
    }

    /**
     * @param string $key
     * @param int $number
     * @param int $ttl
     * @return int
     */
    public function register($key, $number = -1, $ttl = 0) {
        Assertion::notNull(self::$redis, "Redis connection hasn't been set");
        Assertion::string($key, "Number key must be a string");
        Assertion::notEq($key, "", "Number key must be a non empty string");
        Assertion::integer($number, "Temporal numbers must be integers");
        Assertion::notEq($number, 0, "Temporal numbers must be integers different from zero");
        Assertion::integer($ttl, "Time to live (ttl) must be an integer");
        Assertion::true($ttl >= 0, "Time to live (ttl) must be strictly greater than zero");

        $key = $this->identifier . "::" . $key;

        self::$redis->sAdd($this->identifier, $key);
        self::$redis->set($key, $number, $ttl);

        return $this->getCurrentNumber();
    }

    /**
     * @param string $key
     * @return int
     */
    public function delete($key) {
        Assertion::notNull(self::$redis, "Redis connection hasn't been set");
        Assertion::string($key, "Number key must be a string");
        Assertion::notEq($key, "", "Number key must be a non empty string");

        $key = $this->identifier . "::" . $key;

        self::$redis->sRemove($this->identifier, $key);
        self::$redis->delete($key);

        return $this->getCurrentNumber();
    }

    /**
     * Resets the Temporal object to its initial value
     * 
     * @return int Returns the initial value
     */
    public function reset() {
        Assertion::notNull(self::$redis, "Redis connection hasn't been set");

        // Remove the set and check its emptiness
        self::$redis->delete($this->identifier);
        Assertion::count(self::$redis->sMembers($this->identifier), 0);

        return $this->initialNumber;
    }
}