<?php

declare(strict_types=1);

use App\Docker\Services\Commands\NginxRestart;
use App\Docker\Services\Nginx;
use App\Docker\Services\Php;
use App\Docker\Site;
use App\Exceptions\DockerServiceException;
use App\Facades\Env;

beforeEach(function () {
    Env::fake(['RECIPE' => 'test-recipe', 'HOST' => 'foo.test']);
});

it('sets its service name', function () {
    expect(new Nginx())->name()->toBe('nginx');
});

it('sets its yml', function () {
    expect(new Nginx())->yml()->toMatchSnapshot();
});

it('can expose docker host', function () {
    Env::put('EXPOSE_DOCKER_HOST', 1);

    expect(new Nginx())->yml('extra_hosts')->toBe([
        'host.docker.internal:host-gateway',
    ]);
});

it('sets its volumes', function () {
    expect(new Nginx())
        ->toHaveVolume('./src', '/var/www')
        ->toHaveVolume('./services/nginx/nginx.conf', '/etc/nginx//nginx.conf')
        ->toHaveVolume('./services/nginx/sites-available', '/etc/nginx//sites-available');
});

it('adds internal network', function () {
    expect(new Nginx())->toHaveNetwork('test-recipe_internal_network');
});

it('can add reverse proxy network', function () {
    Env::put('REVERSE_PROXY_NETWORK', 'foo-network');

    expect(new Nginx())->toHaveNetwork('foo-network');
});

it('can set php service dependency', function () {
    $php = new Php();
    $nginx = new Nginx();

    $nginx->phpService($php);

    expect($nginx)->yml('depends_on')->toBe(['php']);
});

it('sets up the site from env', function (array $env) {
    collect($env)->each(fn ($value, $key) => Env::put($key, $value));

    $nginx = new Nginx();

    expect($nginx->sites())
        ->toHaveCount(1)
        ->first()->configuration()->toMatchTextSnapshot();
})->with([
    'default' => fn () => [],
    'custom port' => fn () => ['NGINX_PORT' => 42],
    'with websockets' => fn () => ['WEBSOCKET_ENABLED' => 1],
    'ssl' => fn () => ['NGINX_PORT' => 443],
    'ssl with websockets' => fn () => ['NGINX_PORT' => 443, 'WEBSOCKET_ENABLED' => 1],
]);

it('can set an external certificate folder', function () {
    Storage::disk('cwd')->makeDirectory('certificates');
    Env::put('NGINX_PORT', 443)->put('NGINX_EXTERNAL_CERTIFICATE_FOLDER', 'certificates');

    $nginx = new Nginx();

    expect($nginx)->toHaveVolume('certificates', '/etc/letsencrypt');
});

it('requires external certificate folder to exist', function () {
    Env::put('NGINX_PORT', 443)->put('NGINX_EXTERNAL_CERTIFICATE_FOLDER', 'foo');
    new Nginx();
})->throws(DockerServiceException::class, 'Path [foo] not found on host system');

it('map added site port', function () {
    $nginx = new Nginx();
    $nginx->addSite('foo.ktm', 42);

    expect($nginx)->yml('ports')->toBe(['80:80', '42:42']);
});

it('can enable proxy target not found page', function () {
    $nginx = new Nginx();
    $nginx->enableProxyTargetNotFoundPage();

    expect($nginx)->isProxyTargetNotFoundPageEnabled()->toBeTrue();
});

it('can return its sites', function () {
    expect(new Nginx())
        ->sites()->toHaveCount(1)
        ->getSite('foo.test')
        ->toBeInstanceOf(Site::class)
        ->host()->toBe('foo.test');
});

test('commands', function () {
    expect(new Nginx())->commands()->toBe([NginxRestart::class]);
});
