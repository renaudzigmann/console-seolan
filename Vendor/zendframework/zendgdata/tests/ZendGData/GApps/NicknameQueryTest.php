<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   ZendGData
 */

namespace ZendGDataTest\GApps;

/**
 * @category   Zend
 * @package    ZendGData\GApps
 * @subpackage UnitTests
 * @group      ZendGData
 * @group      ZendGData\GApps
 */
class NicknameQueryTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        $this->query = new \ZendGData\GApps\NicknameQuery();
    }

    // Test to make sure that URI generation works
    public function testDefaultQueryURIGeneration()
    {
        $this->query->setDomain("foo.bar.invalid");
        $this->assertEquals("https://apps-apis.google.com/a/feeds/foo.bar.invalid/nickname/2.0",
                $this->query->getQueryUrl());
    }

    // Test to make sure that the domain accessor methods work and propagate
    // to the query URI.
    public function testCanSetQueryDomain()
    {
        $this->query->setDomain("my.domain.com");
        $this->assertEquals("my.domain.com", $this->query->getDomain());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0",
                $this->query->getQueryUrl());

        $this->query->setDomain("hello.world.baz");
        $this->assertEquals("hello.world.baz", $this->query->getDomain());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/hello.world.baz/nickname/2.0",
                $this->query->getQueryUrl());
    }

    // Test to make sure that the username accessor methods work and propagate
    // to the query URI.
    public function testCanSetUsernameProperty()
    {
        $this->query->setDomain("my.domain.com");
        $this->query->setUsername("foo");
        $this->assertEquals("foo", $this->query->getUsername());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0?username=foo",
                $this->query->getQueryUrl());

        $this->query->setUsername(null);
        $this->assertEquals(null, $this->query->getUsername());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0",
                $this->query->getQueryUrl());
    }

    // Test to make sure that the nickname accessor methods work and propagate
    // to the query URI.
    public function testCanSetNicknameProperty()
    {
        $this->query->setDomain("my.domain.com");
        $this->query->setNickname("foo");
        $this->assertEquals("foo", $this->query->getNickname());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0/foo",
                $this->query->getQueryUrl());

        $this->query->setNickname("bar");
        $this->assertEquals("bar", $this->query->getNickname());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0/bar",
                $this->query->getQueryUrl());
    }

    // Test to make sure that the startNickname accessor methods work and
    // propagate to the query URI.
    public function testCanSetStartNicknameProperty()
    {
        $this->query->setDomain("my.domain.com");
        $this->query->setNickname("foo");
        $this->query->setStartNickname("bar");
        $this->assertEquals("bar", $this->query->getStartNickname());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0/foo?startNickname=bar",
                $this->query->getQueryUrl());

        $this->query->setStartNickname(null);
        $this->assertEquals(null, $this->query->getStartNickname());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0/foo",
                $this->query->getQueryUrl());
    }


    // Test to make sure that all parameters can be set simultaneously with no
    // ill effects.
    public function testCanSetAllParameters()
    {
        $this->query->setDomain("my.domain.com");
        $this->query->setNickname("foo");
        $this->query->setUsername("bar");
        $this->query->setStartNickname("baz");
        $this->assertEquals("foo", $this->query->getNickname());
        $this->assertEquals("bar", $this->query->getUsername());
        $this->assertEquals("baz", $this->query->getStartNickname());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0/foo?username=bar&startNickname=baz",
                $this->query->getQueryUrl());

        $this->query->setUsername("qux");
        $this->query->setNickname("baz");
        $this->query->setStartNickname("wibble");
        $this->assertEquals("baz", $this->query->getNickname());
        $this->assertEquals("qux", $this->query->getUsername());
        $this->assertEquals("wibble", $this->query->getStartNickname());
        $this->assertEquals("https://apps-apis.google.com/a/feeds/my.domain.com/nickname/2.0/baz?username=qux&startNickname=wibble",
                $this->query->getQueryUrl());
    }

}
