<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace App\Docker\Services;

use App\Docker\Service;
use App\Docker\ServiceDefinition;
use App\Docker\Services\Commands\Npm;
use App\Enums\EnvKey;

class Node extends Service
{
    protected int|string $version = 'lts';

    protected function configure(): void
    {
        $this->setServiceName('node');

        $this->serviceDefinition = new ServiceDefinition([
            'working_dir' => '/var/www',
            'build' => [
                'context' => "{$this->assetsFolder()}/build",
            ],
            'user' => "{$this->getUserId()}:{$this->getGroupId()}",
        ]);

        $this->version($this->env(EnvKey::node_version, 'lts'));

        if (!$this->isProductionMode()) {
            $this->mapPort(5173); //Vite port
        }

        $this->addVolume(self::HOST_SRC_PATH, '/var/www');

        $this->addNetwork($this->internalNetworkName());
    }

    public function version(string|int $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getNodeVersion(): string|int
    {
        return $this->version;
    }

    public function commands(): array
    {
        return [
            Commands\Node::class,
            Npm::class,
        ];
    }

    public function publishAssets(): void
    {
        $this->publishDockerfile();
    }

    private function publishDockerfile(): void
    {
        $this->assets()->put(
            self::ASSET_DOCKERFILE_PATH,
            view('services.node.dockerfile.main')->with('service', $this)->render()
        );
    }
}
