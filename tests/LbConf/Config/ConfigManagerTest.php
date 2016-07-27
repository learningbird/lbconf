<?php
namespace Tests\LbConf\Config;

use LbConf\Config\ConfigManager;

class ConfigManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var ConfigManager
     */
    protected $mockConfigManager;

    protected function setUp()
    {
        $this->configManager = new ConfigManager();
        $this->configManager->loadConfig(__DIR__ . '/fixtures/.lbconf');

        $this->mockConfigManager = $this->getMockBuilder(ConfigManager::class)
            ->setMethods(['writeConfigFile'])
            ->getMock();
        $this->mockConfigManager->loadConfig(__DIR__ . '/fixtures/.lbconf');
    }

    /**
     * @expectedException \DomainException
     */
    public function testMetaConfigMissingWrite()
    {
        $config = [];
        file_put_contents('/tmp/.lbconf-bad', json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);

        try {
            $configManager = new ConfigManager();
            $configManager->loadConfig('/tmp/.lbconf-bad');
        } finally {
            unlink('/tmp/.lbconf-bad');
        }
    }

    /**
     * @expectedException \DomainException
     */
    public function testMetaConfigInvalidWriteType()
    {
        $config = [
            'write' => []
        ];
        file_put_contents('/tmp/.lbconf-bad', json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);

        try {
            $configManager = new ConfigManager();
            $configManager->loadConfig('/tmp/.lbconf-bad');
        } finally {
            unlink('/tmp/.lbconf-bad');
        }
    }

    /**
     * @expectedException \Exceptions\IO\Filesystem\FileNotFoundException
     */
    public function testMetaConfigMissingReadFile()
    {
        $config = [
            'read' => [
                'missing-file.json',
            ],
            'write' => 'file.json',
        ];
        file_put_contents('/tmp/.lbconf-bad', json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);

        try {
            $configManager = new ConfigManager();
            $configManager->loadConfig('/tmp/.lbconf-bad');
        } finally {
            unlink('/tmp/.lbconf-bad');
        }
    }

    public function testGetDefault()
    {
        $this->assertEquals('456', $this->configManager->get('d.d2'));
    }

    /**
     * @expectedException \Exceptions\Collection\KeyNotFoundException
     */
    public function testGetNonExistent()
    {
        $this->configManager->get('nonExistentKey');
    }

    /**
     * @expectedException \Exceptions\Collection\KeyNotFoundException
     */
    public function testGetNonExistentSubKey()
    {
        $this->configManager->get('a.nonExistentKey');
    }

    public function testGetOverride()
    {
        $this->assertEquals('override', $this->configManager->get('d.d1'));
    }

    public function testGetAll()
    {
        $all  = $this->configManager->get();
        $keys = array_keys($all);
        sort($keys);

        $this->assertEquals(["a", "b", "c", "d", "e", "f"], $keys);
    }

    public function testSet()
    {
        $this->configManager->set('d.d3.d31', 'test');

        $expected  = [
            'd1' => 'override',
            'd3' => [
                'd31' => 'test',
            ],
            'd4' => 'value',
        ];
        $writeData = $this->configManager->getWriteData();

        $this->assertArrayHasKey('d', $writeData);
        $this->assertEquals($expected, $writeData['d']);
    }

    public function testKeys()
    {
        return $this->assertEquals([
            "d1", "d2", "d3", "d4",
        ], $this->configManager->keys('d'));
    }

    public function testKeysAll()
    {
        $keys = $this->configManager->keys();
        sort($keys);

        $this->assertEquals(["a", "b", "c", "d", "e", "f"], $keys);
    }

    public function testKeysScalar()
    {
        return $this->assertEquals([], $this->configManager->keys('b'));
    }

    public function testDelete()
    {
        $this->configManager->delete('d.d1');

        $writeData = $this->configManager->getWriteData();

        $this->assertArrayHasKey('d', $writeData);
        $this->assertArrayNotHasKey('d1', $writeData['d']);
    }

    public function testDeleteTopLevel()
    {
        $this->configManager->delete('d');

        $writeData = $this->configManager->getWriteData();

        $this->assertArrayNotHasKey('d', $writeData);
    }

    /**
     * @expectedException \Exceptions\Collection\KeyNotFoundException
     */
    public function testDeleteNonExistent()
    {
        $this->configManager->delete('d.nonExistentKey');
    }

    public function testStore()
    {
        $originalData = $this->mockConfigManager->getWriteData();

        $expected = [
            'd' => [
                'd1' => 'override',
                'd3' => [
                    'd31' => 'test',
                ],
                'd4' => 'value',
            ],
        ];

        $this->mockConfigManager->set('d.d3.d31', 'test');
        $writeData = $this->mockConfigManager->getWriteData();

        $this->assertEquals($writeData, array_merge($originalData, $expected));

        $this->mockConfigManager->expects($this->once())
            ->method('writeConfigFile')
            ->with(
                $this->stringContains('override.json'),
                $this->callback(function ($data) use ($writeData) {
                    $this->assertEquals($writeData, $data);
                    return true;
                }
                ));

        $this->mockConfigManager->storeConfig();
    }

    public function testNumberCast()
    {
        $this->configManager->set('b', '0.1');
        $this->assertSame(0.1, $this->configManager->get('b'));

        $this->configManager->set('b', '0.1', 'number');
        $this->assertSame(0.1, $this->configManager->get('b'));

        $this->configManager->set('b', '1', 'number');
        $this->assertSame(1, $this->configManager->get('b'));

        $this->configManager->set('b', '4ever');
        $this->assertSame('4ever', $this->configManager->get('b'));

        $this->configManager->set('b', '4ever', 'number');
        $this->assertSame(4, $this->configManager->get('b'));
    }

    public function testBooleanCast()
    {
        $this->configManager->set('c', 'true');
        $this->assertSame(true, $this->configManager->get('c'));

        $this->configManager->set('c', 'false');
        $this->assertSame(false, $this->configManager->get('c'));

        $this->configManager->set('c', 'true', 'boolean');
        $this->assertSame(true, $this->configManager->get('c'));

        $this->configManager->set('c', true);
        $this->assertSame(true, $this->configManager->get('c'));

        $this->configManager->set('c', 'astring', 'boolean');
        $this->assertSame(true, $this->configManager->get('c'));
    }

    public function testStringCast()
    {
        $this->configManager->set('c', 'true', 'string');
        $this->assertSame('true', $this->configManager->get('c'));

        $this->configManager->set('b', '0.1', 'string');
        $this->assertEquals(0.1, $this->configManager->get('b'), '', 0.001);
    }

    public function testNullCast()
    {
        $this->configManager->set('c', 'null');
        $this->assertSame(null, $this->configManager->get('c'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidCast()
    {
        $this->configManager->set('c', 'string', 'othertype');
    }
}
