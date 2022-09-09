<?php

/** @noinspection PhpUnused */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace App\Docker;

use App\Exceptions\DockerServiceException;
use App\Facades\Env;
use App\Recipes\Recipe;
use App\Services\RecipeService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

abstract class Service
{
    protected static bool $fake = false;
    protected bool $faked = false;

    protected const HOST_SRC_PATH = './src';

    protected const HOST_SERVICES_PATH = './services';

    protected ServiceDefinition $serviceDefinition;

    /** @var Collection<int, Volume> */
    private Collection $volumes;

    /** @var Collection<string, Network> */
    private Collection $networks;

    protected string $name;

    public function __construct()
    {
        $this->volumes = Collection::empty();
        $this->networks = Collection::empty();

        $this->configure();

        if (!isset($this->serviceDefinition)) {
            throw DockerServiceException::serviceNotConfigured($this->name);
        }
    }

    abstract protected function configure(): void;

    public function name(): string
    {
        return $this->name;
    }

    public function setServiceName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    protected function recipe(): Recipe
    {
        $cookbook = app(RecipeService::class);

        return $cookbook->recipe();
    }

    public function addVolume(string $hostPath, string $containerPath = null): static
    {
        $this->volumes->push(app(Volume::class, ['hostPath' => $hostPath, 'containerPath' => $containerPath]));

        return $this;
    }

    /** @return Collection<int, Volume> */
    public function volumes(): Collection
    {
        return $this->volumes;
    }

    public function mapPort(int $hostPort, int $containerPort = null): static
    {
        $containerPort ??= $hostPort;

        $this->serviceDefinition->push('ports', "$hostPort:$containerPort");
        $this->exposePort($containerPort);

        return $this;
    }

    public function exposePort(int $port): static
    {
        $this->serviceDefinition->push('expose', $port);

        return $this;
    }

    public function dependsOn(string $serviceName): static
    {
        $this->serviceDefinition->push('depends_on', $serviceName);

        return $this;
    }

    /** @return Collection<string, Network> */
    public function getNetworks(): Collection
    {
        return $this->networks;
    }

    public function addNetwork(string $name): Network
    {
        $network = app(Network::class, ['name' => $name]);

        $this->networks = $this->networks->put($name, $network);

        return $network;
    }

    /**
     * @return class-string<Command>[]
     */
    public function commands(): array
    {
        return [];
    }

    protected function assetsFolder(): string
    {
        return self::HOST_SERVICES_PATH."/$this->name";
    }

    public function publishAssets(): void
    {
        // No asset by default
    }

    public function assets(): Filesystem
    {
        if (self::$fake) {
            $fakeDiskName = Str::of($this->assetsFolder())
                ->slug()
                ->when(ParallelTesting::token(), fn(Stringable $str, string $token) => $str->append("_test_$token"))
                ->toString();

            if (!$this->faked) {
                $this->faked = true;
                Storage::persistentFake($fakeDiskName)->deleteDirectory('.');
            }

            return Storage::persistentFake($fakeDiskName);
        }


        return Storage::build([
            'driver' => 'local',
            'root' => getcwd()."/{$this->assetsFolder()}",
        ]);
    }

    public static function fake(): void
    {
        self::$fake = true;
    }

    protected function isProductionMode(): bool
    {
        return Env::get('ENV') === 'production';
    }

    protected function isDockerHostExposed(): bool
    {
        return (bool) Env::get('EXPOSE_DOCKER_HOST', false);
    }

    public function internalNetworkName(): string
    {
        return $this->recipe()->slug().'_internal_network';
    }

    public function getUserId(): int
    {
        $uid = Env::get('USER_ID', getmyuid());

        if ($uid === false) {
            throw DockerServiceException::unableToDetectCurrentUserId();
        }

        return $uid;
    }

    public function getGroupId(): int
    {
        $uid = Env::get('GROUP_ID', Env::get('USER_ID', getmyuid()));

        if ($uid === false) {
            throw DockerServiceException::unableToDetectCurrentGroupId();
        }

        return $uid;
    }

    public function host(): string
    {
        $host = Env::get('HOST');

        if (empty($host)) {
            throw DockerServiceException::missingHost();
        }

        return $host;
    }

    protected function getWorkingDir(): string
    {
        return (string) $this->serviceDefinition->get('working_dir');
    }

    protected function isBehindReverseProxy(): bool
    {
        return !empty($this->reverseProxyNexwork());
    }

    protected function reverseProxyNexwork(): string
    {
        return (string) Env::get('REVERSE_PROXY_NETWORK', '');
    }

    public function yml(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->serviceDefinition->toArray();
        }

        return $this->serviceDefinition->get($key, $default);
    }
}