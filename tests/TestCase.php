<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        // Set the test database before Laravel reads the Docker environment.
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('DB_URL=');
        putenv('SESSION_DRIVER=array');
        putenv('CACHE_STORE=array');

        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['DB_URL'] = '';
        $_ENV['SESSION_DRIVER'] = 'array';
        $_ENV['CACHE_STORE'] = 'array';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_URL'] = '';
        $_SERVER['SESSION_DRIVER'] = 'array';
        $_SERVER['CACHE_STORE'] = 'array';

        return parent::createApplication();
    }
}
