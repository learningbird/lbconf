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

    public function testGetDefault()
    {
        $this->assertEquals('149.95', $this->configManager->get('subscription.plans.annual.price'));
    }

    /**
     * @expectedException \Exceptions\Collection\KeyNotFoundException
     */
    public function testGetNonExistent()
    {
        $this->configManager->get('subscription.plans.biennial.price');
    }

    public function testGetOverride()
    {
        $this->assertEquals(0.1, $this->configManager->get('feedback.timeWatchedFactor'));
    }

    public function testGetAll()
    {
        $all  = $this->configManager->get();
        $keys = array_keys($all);
        sort($keys);

        $this->assertEquals([
            "database",
            "debugEnabled",
            "feedback",
            "gradeCoverage",
            "httpOrHttps",
            "loggly",
            "mail",
            "maintenanceMode",
            "profile",
            "raven",
            "s3",
            "services",
            "subdomain",
            "subscription",
            "taxes",
            "uploads",
        ], $keys);
    }

    public function testSet()
    {
        $this->configManager->set('subscription.plans.annual.price', '99.95');

        $expected  = [
            'plans' => [
                'annual' => [
                    'price' => '99.95',
                ],
            ],
        ];
        $writeData = $this->configManager->getWriteData();

        $this->assertArrayHasKey('subscription', $writeData);
        $this->assertEquals($expected, $writeData['subscription']);
    }

    public function testKeys()
    {
        return $this->assertEquals([
            "private",
            "public",
        ], $this->configManager->keys('gradeCoverage'));
    }

    public function testKeysAll()
    {
        $keys = $this->configManager->keys();
        sort($keys);

        return $this->assertEquals([
            "database",
            "debugEnabled",
            "feedback",
            "gradeCoverage",
            "httpOrHttps",
            "loggly",
            "mail",
            "maintenanceMode",
            "profile",
            "raven",
            "s3",
            "services",
            "subdomain",
            "subscription",
            "taxes",
            "uploads",
        ], $keys);
    }

    public function testKeysScalar()
    {
        return $this->assertEquals([], $this->configManager->keys('gradeCoverage.private.min'));
    }

    public function testDelete()
    {
        $this->configManager->delete('feedback.timeWatchedFactor');

        $writeData = $this->configManager->getWriteData();

        $this->assertArrayHasKey('feedback', $writeData);
        $this->assertArrayNotHasKey('timeWatchedFactor', $writeData['feedback']);
    }

    public function testDeleteTopLevel()
    {
        $this->configManager->delete('feedback');

        $writeData = $this->configManager->getWriteData();

        $this->assertArrayNotHasKey('timeWatchedFactor', $writeData);
    }

    /**
     * @expectedException \Exceptions\Collection\KeyNotFoundException
     */
    public function testDeleteNonExistent()
    {
        $this->configManager->delete('feedback.nonExistentKey');
    }

    public function testStore()
    {
        $originalData = $this->mockConfigManager->getWriteData();

        $expected = [
            'subscription' => [
                'plans' => [
                    'annual' => [
                        'price' => '99.95',
                    ],
                ],
            ],
        ];

        $this->mockConfigManager->set('subscription.plans.annual.price', '99.95');
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
        $this->configManager->set('feedback.timeWatchedFactor', '0.1');
        $this->assertSame(0.1, $this->configManager->get('feedback.timeWatchedFactor'));

        $this->configManager->set('feedback.timeWatchedFactor', '0.1', 'number');
        $this->assertSame(0.1, $this->configManager->get('feedback.timeWatchedFactor'));

        $this->configManager->set('feedback.timeWatchedFactor', '1', 'number');
        $this->assertSame(1, $this->configManager->get('feedback.timeWatchedFactor'));

        $this->configManager->set('feedback.timeWatchedFactor', '4ever');
        $this->assertSame('4ever', $this->configManager->get('feedback.timeWatchedFactor'));

        $this->configManager->set('feedback.timeWatchedFactor', '4ever', 'number');
        $this->assertSame(4, $this->configManager->get('feedback.timeWatchedFactor'));
    }

    public function testBooleanCast()
    {
        $this->configManager->set('debugEnabled', 'true');
        $this->assertSame(true, $this->configManager->get('debugEnabled'));

        $this->configManager->set('debugEnabled', 'false');
        $this->assertSame(false, $this->configManager->get('debugEnabled'));

        $this->configManager->set('debugEnabled', 'true', 'boolean');
        $this->assertSame(true, $this->configManager->get('debugEnabled'));

        $this->configManager->set('debugEnabled', true);
        $this->assertSame(true, $this->configManager->get('debugEnabled'));

        $this->configManager->set('debugEnabled', 'astring', 'boolean');
        $this->assertSame(true, $this->configManager->get('debugEnabled'));
    }

    public function testStringCast()
    {
        $this->configManager->set('debugEnabled', 'true', 'string');
        $this->assertSame('true', $this->configManager->get('debugEnabled'));

        $this->configManager->set('feedback.timeWatchedFactor', '0.1', 'string');
        $this->assertEquals(0.1, $this->configManager->get('feedback.timeWatchedFactor'), '', 0.001);
    }

    public function testNullCast()
    {
        $this->configManager->set('debugEnabled', 'null');
        $this->assertSame(null, $this->configManager->get('debugEnabled'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidCast()
    {
        $this->configManager->set('debugEnabled', 'string', 'othertype');
    }
}
