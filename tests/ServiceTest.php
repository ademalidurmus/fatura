<?php namespace AAD\Fatura;

use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public function testCurrencyTransformerToWords()
    {
        $service = new Service();
        $text = $service->currencyTransformerToWords("10025");

        $this->assertEquals("YÜZ TÜRK LIRASI YIRMI BEŞ KURUŞ", $text);
    }

    public function testSetGetConfig()
    {
        $service = new Service();
        $service->setConfig("test_key", "test_val");
        $config_val = $service->getConfig("test_key");

        $this->assertEquals("test_val", $config_val);
    }
}
