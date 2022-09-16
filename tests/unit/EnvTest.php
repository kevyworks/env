<?php

class EnvTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        # curl -LO https://phar.phpunit.de/phpunit-5.7.phar &&
        # mv -f phpunit-5.7.phar /usr/local/bin/phpunit &&
        # chmod +x /usr/local/bin/phpunit
        require_once __DIR__ . '/../../Env.php';

        Env::loadEnvFile(__DIR__ . '/.env_test', false, Env::MODE_ENV);
    }

    public function test_env()
    {
        $this->assertEquals($_ENV, Env::readAll());
    }

    public function test_env_contains()
    {
        $this->assertArrayHasKey('MAIL_HOST', $_ENV);
    }

    public function test_variable_ref()
    {
        $this->assertEquals('HELLO WORLD', Env::getEnv('TEST_REF'));
    }

    public function test_must_be_array()
    {
        $result = Env::get('TEST_ARRAY');

        $this->assertEquals($result, explode(',', 'a,b,c,d'));
    }

    public function test_prefixed_array()
    {
        $result = Env::getArray('MAIL_', null, true);

        $this->assertSame(['HOST', 'SMTPAUTH', 'PORT', 'USERNAME', 'PASSWORD'], array_keys($result));
    }

    public function test_must_be_json_array()
    {
        $this->assertCount(3, Env::get('TEST_JSON_ARRAY'));
    }

    public function test_must_be_json_object()
    {
        $this->assertObjectHasAttribute('c', Env::get('TEST_JSON'));
    }

    public function test_false()
    {
        $this->assertEquals(true, Env::getEnv('TEST_FALSE') === false);
    }

    public function test_true()
    {
        $this->assertEquals(true, Env::getEnv('TEST_TRUE'));
    }

    public function test_null()
    {
        $this->assertNull(Env::getEnv('TEST_NULL'));
    }

    public function test_int()
    {
        $this->assertEquals(123, Env::getEnv('TEST_INT'));
    }

    public function test_float()
    {
        $this->assertEquals(99.99, Env::getEnv('TEST_FLOAT'));
    }
}
