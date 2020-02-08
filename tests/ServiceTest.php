<?php namespace AAD\Fatura;

use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public $options = [
        "token" => "unittest"
    ];

    public function testCurrencyTransformerToWords()
    {
        $service = new Service($this->options);
        $text = $service->currencyTransformerToWords("10025");

        $this->assertEquals("YÜZ TÜRK LIRASI YIRMI BEŞ KURUŞ", $text);
    }

    public function testSetGetConfig()
    {
        $service = new Service($this->options);
        $service->setConfig("test_key", "test_val");
        $config_val = $service->getConfig("test_key");

        $this->assertEquals("test_val", $config_val);
    }

    public function testSetGetUuid()
    {
        $service = new Service($this->options);
        $service->setUuid("590e1a3e-4aaf-11ea-b085-8434976ef848");
        $uuid = $service->getUuid();

        $this->assertEquals("590e1a3e-4aaf-11ea-b085-8434976ef848", $uuid);
    }

    public function testGetRandomUuid()
    {
        $service = new Service($this->options);
        
        $this->assertNotEquals($service->getUuid(), $service->getUuid());
    }
}
