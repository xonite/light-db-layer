<?php

namespace LightDBLayer\Tests;

use LightDBLayer\Entity;
use PHPUnit\Framework\TestCase;

class FakeEntity extends Entity {
    public ?int $id;
    public string $name;
    public \DateTime $date;

    public function dateFormatter(): string
    {
        return 5;
    }
}

class EntityTest extends TestCase
{
    public function testSingleGetArray()
    {
        $fake = new FakeEntity();
        $fake->id = 5;
        $this->assertEquals(['id' => 5], $fake->getArray());
    }

    public function testFormattedGetArray()
    {
        $fake = new FakeEntity();
        $fake->id = 1;
        $fake->date = new \DateTime();
        $this->assertEquals(['id' => 1, 'date' => 5], $fake->getArray());
    }

    public function testGetArrayWithNull()
    {
        $fake = new FakeEntity();
        $fake->id = null;
        $fake->name = 'lol';
        $this->assertEquals(['id' => null, 'name' => 'lol'], $fake->getArray());
    }

    public function testEmptyGetArray()
    {
        $fake = new FakeEntity();
        $this->assertEquals([], $fake->getArray());
    }

    public function testHydration()
    {
        $fake = new FakeEntity();
        $fake->id = 1;
        $hydrated = new FakeEntity();
        $hydrated->hydrate((object)$fake->getArray());
        $this->assertEquals($fake->getArray(), $hydrated->getArray());
    }
}
