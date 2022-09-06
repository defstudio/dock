<?php

/** @noinspection LaravelFunctionsInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace App\Recipes\Laravel\Services;

use App\Docker\Services\Php;

class Websocket extends Php
{
    protected function configure(): void
    {
        parent::configure();

        $this->setServiceName('websocket');

        $this->target('websocket');

        $this->dependsOn('php');

        if ($this->isBehindReverseProxy()) {
            $this->addNetwork($this->reverseProxyNexwork());
        } else {
            $port = (int) env('WEBSOCKET_PORT');
            $this->mapPort($port, 6001);
        }
    }
}
