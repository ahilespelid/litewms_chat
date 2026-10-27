<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * В одном процессе гвард Sanctum помнит пользователя предыдущего запроса. В жизни каждый запрос
     * приходит в чистый процесс, поэтому перед каждым вызовом забываем кэш — иначе тест «от имени Б»
     * тихо выполнялся бы от имени А.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
