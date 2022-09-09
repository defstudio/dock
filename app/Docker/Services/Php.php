<?php

/** @noinspection PhpUnused */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace App\Docker\Services;

use App\Docker\Service;
use App\Docker\ServiceDefinition;
use App\Exceptions\DockerServiceException;
use App\Facades\Env;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Php extends Service
{
    protected float|string $version;

    protected array $allowedTargets = [
        'fpm', 'composer', 'scheduler', 'websocket', 'worker',
    ];

    protected function configure(): void
    {
        $this->setServiceName('php');

        $this->serviceDefinition = new ServiceDefinition([
            'restart' => 'unless-stopped',
            'working_dir' => '/var/www',
            'build' => [
                'context' => $this->assetsFolder(),
                'target' => 'fpm',
            ],
            'expose' => [9000],
            'environment' => ['DOCK' => 1],
        ]);

        if ($this->isDockerHostExposed()) {
            $this->serviceDefinition->push('extra_hosts', 'host.docker.internal:host-gateway');
        }

        if (Env::get('REDIS_ENABLED')) {
            $this->dependsOn(app(Redis::class)->name());
        }

        if (Env::get('DB_ENGINE') === 'mysql') {
            $this->dependsOn(app(MySql::class)->name());
        }

        $this->version(Env::get('PHP_VERSION', 'latest'));

        $this->addVolume(self::HOST_SRC_PATH, $this->getWorkingDir());

        $this->addNetwork($this->internalNetworkName());
    }

    public function target(string $target): static
    {
        if (!in_array($target, $this->allowedTargets)) {
            throw DockerServiceException::generic("Invalid PHP target: [$target]");
        }

        $this->serviceDefinition->set('build.target', $target);

        return $this;
    }

    public function getTarget(): string
    {
        return (string) $this->serviceDefinition->get('build.target');
    }

    public function version(string|float $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getPhpVersion(): string
    {
        return "$this->version";
    }

    public function isXdebugEnabled(): bool
    {
        if ($this->isProductionMode()) {
            return false;
        }

        return Str::of(Env::get('EXTRA_TOOLS'))
            ->explode(',')
            ->each(fn (string $tool) => trim($tool))
            ->contains('xdebug');
    }

    public function isPcovEnabled(): bool
    {
        if ($this->isProductionMode()) {
            return false;
        }

        return Str::of(Env::get('EXTRA_TOOLS'))
            ->explode(',')
            ->each(fn (string $tool) => trim($tool))
            ->contains('pcov');
    }

    public function isLibreOfficeWriterEnabled(): bool
    {
        if($this->phpMajorVersion() < 7.0){
            return false;
        }

        return Str::of(Env::get('EXTRA_TOOLS'))
            ->explode(',')
            ->each(fn (string $tool) => trim($tool))
            ->contains('libreoffice_writer');
    }

    public function isMySqlClientEnabled(): bool
    {
        return Str::of(Env::get('EXTRA_TOOLS'))
            ->explode(',')
            ->each(fn (string $tool) => trim($tool))
            ->contains('mysql_client');
    }

    /**
     * @return Collection<int, string>
     */
    public function systemPackages(): Collection
    {
        //TODO Check required and optional installs
        $installs = [
            'curl' => true,
            'ping' => false,
            'nano' => true,
            'git' => true,
            'unzip' => true,
            'sqlite3' => true,
            'default-mysql-client' => $this->isMySqlClientEnabled(),

            'libmemcached-dev' => true,
            'libz-dev' => true,
            'libjpeg-dev' => true,
            'libpng-dev' => true,
            'libssl-dev' => true,
            'libmcrypt-dev' => true,
            'libzip-dev' => true,
            'libfreetype6-dev' => true,
            'libjpeg62-turbo-dev' => true,
            'libxml2-dev' => true,
            'libxrender1' => true,
            'libfontconfig1' => true,
            'libxext6' => true,
            'ca-certificates' => true,
            'libnss3' => true,
        ];

        return collect($installs)->filter()->keys();
    }

    /**
     * @return Collection<int, string>
     */
    public function phpExtensions(): Collection
    {
        //TODO Check required and optional extensions
        $installs = [
            'pdo_mysql' => Env::get('DB_ENGINE', 'mysql') === 'mysql',
            'mysqli' => Env::get('DB_ENGINE', 'mysql') === 'mysql',
            'pcntl' => true,
            'zip' => true,
            'soap' => true,
            'intl' => true,
            'gettext' => true,
            'exif' => true,
            'gd' => true,
        ];

        return collect($installs)->filter()->keys();
    }

    public function isRedisEnabled(): bool
    {
        if($this->phpMajorVersion() < 7){
            return false;
        }

        return !!Env::get('REDIS_ENABLED');
    }

    public function phpMajorVersion(): int
    {
        if ($this->version === 'latest') {
            return 8;
        }

        return (int) $this->version;
    }

    public function getPhpMinorVersion(): float
    {
        if ($this->version === 'latest') {
            return 8.1;
        }

        return round(floatval($this->version), 1, PHP_ROUND_HALF_DOWN);
    }

    protected function assetsFolder(): string
    {
        return self::HOST_SERVICES_PATH.'/php';
    }

    public function publishAssets(): void
    {
        $this->publishPhpIni();
        $this->publishDockerfile();
    }

    private function publishDockerfile(): void
    {
        $dockerfile = view('services.php.dockerfile.main')->with('service', $this)->render();
        $this->assets()->put('Dockerfile', $dockerfile);
    }

    private function publishPhpIni(): void
    {
        $phpini = view('services.php.php_ini')
            ->with('production', $this->isProductionMode())
            ->with('expose_php', false)
            ->with('max_execution_time', CarbonInterval::minutes(10)->totalSeconds)
            ->with('max_input_time', CarbonInterval::minutes(2)->totalSeconds)
            ->with('max_input_nesting_level', 1024)
            ->with('max_input_vars', 100000)
            ->with('allow_file_uploads', true)
            ->with('memory_limit', '2G')
            ->with('post_max_size', '2G')
            ->with('upload_max_filesize', '2G')
            ->with('max_file_uploads', 1000)
            ->with('display_errors', !$this->isProductionMode())
            ->with('log_errors', true)
            ->render();

        $this->assets()->put('php.ini', $phpini);
    }
}