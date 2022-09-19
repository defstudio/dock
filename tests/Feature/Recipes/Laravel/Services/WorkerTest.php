<?php

declare(strict_types=1);

use App\Docker\Service;
use App\Facades\Env;
use App\Recipes\Laravel\Services\Scheduler;
use App\Recipes\Laravel\Services\Worker;

beforeEach(function () {
    Env::fake(['RECIPE' => 'test-recipe', 'HOST' => 'foo']);
});

it('sets its service name', function () {
    expect(new Worker())->name()->toBe('worker');
});

it('sets its yml', function () {
    expect(new Worker())->yml()->toMatchSnapshot();
});

it('sets its target', function () {
    expect(new Worker())->yml('build.target')->toBe('worker');
});

test('commands', function () {
    expect(new Worker())->commands()->toBe([]);
});

it('publishes assets', function (string $asset, array $env, string $phpVersion) {
    Env::fake($env)->put(\App\Enums\EnvKey::php_version, $phpVersion);
    Service::fake();

    $scheduler = new Scheduler();
    $scheduler->publishAssets();

    expect($scheduler->assets()->get($asset))->toMatchSnapshot();
})->with([
    'build/Dockerfile',
    'build/worker/start_script.sh',
])->with([
    'default' => fn () => ['RECIPE' => 'test-recipe', 'HOST' => 'foo'],
])->with('php versions');
