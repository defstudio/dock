<?php

declare(strict_types=1);

use App\Facades\Terminal;
use App\Recipes\Laravel\Laravel;
use Illuminate\Support\Facades\Storage;

test('setup', function (array $steps, array $config) {
    Terminal::fake($steps);
    Storage::fake('cwd');

    $recipe = new Laravel();
    $configuration = $recipe->setup();

    Terminal::assertAllExpectedMessageSent();

    foreach ($config as $key => $value) {
        expect($configuration->get($key))->toBe($value);
    }
})->with([
    'default path' => [
        'steps' => [
            'General',
            'Application hostname' => 'test.ktm',
            'Application environment' => 'local',
            'PHP Version' => '8.1',
            'Should Docker Host be exposed to containers (Docker > v20.04 only)?' => 'no',
            'Is the application behind a reverse proxy?' => 'no',
            'Install any extra tools?' => 'xdebug',
            '<2>Install any extra tools?' => 'mysql_client',
            '<3>Install any extra tools?' => '',
            'Network Configuration',
            'Do you want to set up a custom ssl certificate? This setup will allow you to define an external folder to load ssl certificates into nginx setup Note: the folder must contain at least the following files: - live/[hostname]/fullchain.pem - live/[hostname]/privkey.pem Do you want to proceed?' => 'no',
            'Enter nginx exposed port' => '',
            'Enter mysql exposed port' => '',
            'Enter PHPMyAdmin exposed port' => 'x',
            'Enter PHPMyAdmin exposed subdomain' => 'x',
            'Enter MailHog exposed port' => 'x',
            'Enter MailHog exposed subdomain' => 'x',
            'Enter Websocket server exposed port' => 'x',
            'Database Configuration',
            'Database name' => '',
            'Database user' => '',
            'Database password' => '',
            'Database root password' => '',
            'SUCCESS!',
            'The configuration has been stored in .env file',
        ],
        'config' => [
            'HOST' => 'test.ktm',
            'ENV' => 'local',
            'PHP_VERSION' => '8.1',
            'EXPOSE_DOCKER_HOST' => false,
            'BEHIND_PROXY' => false,
            'EXTERNAL_CERTIFICATE' => false,
            'NGINX_PORT' => '80',
            'MYSQL_PORT' => '3306',
            'PHPMYADMIN_PORT' => '',
            'PHPMYADMIN_SUBDOMAIN' => '',
            'MAILHOG_PORT' => '',
            'MAILHOG_SUBDOMAIN' => '',
            'WEBSOCKET_PORT' => '',
            'MYSQL_DATABASE' => 'database',
            'MYSQL_USER' => 'dbuser',
            'MYSQL_PASSWORD' => 'dbpassword',
            'MYSQL_ROOT_PASSWORD' => 'root',
        ],
    ],
    'production requires mysql strong root password' => [
        'steps' => [
            'General',
            'Application hostname' => 'test.ktm',
            'Application environment' => 'production',
            'PHP Version' => '8.1',
            'Should Docker Host be exposed to containers (Docker > v20.04 only)?' => 'no',
            'Is the application behind a reverse proxy?' => 'no',
            'Install any extra tools?' => 'xdebug',
            '<2>Install any extra tools?' => 'mysql_client',
            '<3>Install any extra tools?' => '',
            'Network Configuration',
            'Do you want to set up a custom ssl certificate? This setup will allow you to define an external folder to load ssl certificates into nginx setup Note: the folder must contain at least the following files: - live/[hostname]/fullchain.pem - live/[hostname]/privkey.pem Do you want to proceed?' => 'no',
            'Enter nginx exposed port' => '',
            'Enter mysql exposed port' => '',
            'Enter PHPMyAdmin exposed port' => 'x',
            'Enter PHPMyAdmin exposed subdomain' => 'x',
            'Enter MailHog exposed port' => 'x',
            'Enter MailHog exposed subdomain' => 'x',
            'Enter Websocket server exposed port' => 'x',
            'Database Configuration',
            'Database name' => '',
            'Database user' => '',
            'Database password' => '',
            'Database root password' => '',
            "Error: you should not use 'root' in production environments",
            '<2>Database root password' => 'foo',
            'SUCCESS!',
            'The configuration has been stored in .env file',
        ],
        'config' => [
            'ENV' => 'production',
            'MYSQL_ROOT_PASSWORD' => 'foo',
        ],
    ],
    'when behind proxy sets reverse proxy network config' => [
        'steps' => [
            'General',
            'Application hostname' => 'test.ktm',
            'Application environment' => 'local',
            'PHP Version' => '8.1',
            'Should Docker Host be exposed to containers (Docker > v20.04 only)?' => 'no',
            'Is the application behind a reverse proxy?' => 'yes',
            'Install any extra tools?' => '',
            'Network Configuration',
            'Do you want to set up a custom ssl certificate? This setup will allow you to define an external folder to load ssl certificates into nginx setup Note: the folder must contain at least the following files: - live/[hostname]/fullchain.pem - live/[hostname]/privkey.pem Do you want to proceed?' => 'no',
            'Enter mysql exposed port' => '',
            'Enter PHPMyAdmin exposed subdomain' => 'x',
            'Enter MailHog exposed subdomain' => 'x',
            'Enter Websocket server exposed port' => 'x',
            'Database Configuration',
            'Database name' => '',
            'Database user' => '',
            'Database password' => '',
            'Database root password' => '',
            'SUCCESS!',
            'The configuration has been stored in .env file',
        ],
        'config' => [
            'BEHIND_PROXY' => true,
            'REVERSE_PROXY_NETWORK' => 'reverse_proxy_network',
        ],
    ],
    'with custom certificate folder' => [
        'steps' => [
            'General',
            'Application hostname' => 'test.ktm',
            'Application environment' => 'local',
            'PHP Version' => '8.1',
            'Should Docker Host be exposed to containers (Docker > v20.04 only)?' => 'no',
            'Is the application behind a reverse proxy?' => 'no',
            'Install any extra tools?' => '',
            'Network Configuration',
            'Do you want to set up a custom ssl certificate? This setup will allow you to define an external folder to load ssl certificates into nginx setup Note: the folder must contain at least the following files: - live/[hostname]/fullchain.pem - live/[hostname]/privkey.pem Do you want to proceed?' => 'yes',
            'Enter the path to the ssl certificates folder (absolute or relative to dock folder)' => 'foo',
            'Error: Invalid path',
            '<2>Enter the path to the ssl certificates folder (absolute or relative to dock folder)' => '/tmp',
            'Enter the hostname contained in the certificate' => 'test.ktm',
            'Enter nginx exposed port' => '',
            'Enter mysql exposed port' => '',
            'Enter PHPMyAdmin exposed port' => 'x',
            'Enter PHPMyAdmin exposed subdomain' => 'x',
            'Enter MailHog exposed port' => 'x',
            'Enter MailHog exposed subdomain' => 'x',
            'Enter Websocket server exposed port' => 'x',
            'Database Configuration',
            'Database name' => '',
            'Database user' => '',
            'Database password' => '',
            'Database root password' => '',
            'SUCCESS!',
            'The configuration has been stored in .env file',
        ],
        'config' => [
            'EXTERNAL_CERTIFICATE' => true,
            'NGINX_CUSTOM_CERTIFICATES_FOLDER' => '/tmp',
        ],
    ],
    'with custom certificate folder behind proxy' => [
        'steps' => [
            'General',
            'Application hostname' => 'test.ktm',
            'Application environment' => 'local',
            'PHP Version' => '8.1',
            'Should Docker Host be exposed to containers (Docker > v20.04 only)?' => 'no',
            'Is the application behind a reverse proxy?' => 'yes',
            'Install any extra tools?' => '',
            'Network Configuration',
            'Do you want to set up a custom ssl certificate? This setup will allow you to define an external folder to load ssl certificates into nginx setup Note: the folder must contain at least the following files: - live/[hostname]/fullchain.pem - live/[hostname]/privkey.pem Do you want to proceed?' => 'yes',
            'Enter the path to the ssl certificates folder (absolute or relative to dock folder)' => '/tmp',
            'Enter the hostname contained in the certificate' => 'test.ktm',
            'Enter mysql exposed port' => '',
            'Enter PHPMyAdmin exposed subdomain' => 'x',
            'Enter MailHog exposed subdomain' => 'x',
            'Enter Websocket server exposed port' => 'x',
            'Database Configuration',
            'Database name' => '',
            'Database user' => '',
            'Database password' => '',
            'Database root password' => '',
            'SUCCESS!',
            'The configuration has been stored in .env file',
        ],
        'config' => [
            'EXTERNAL_CERTIFICATE' => true,
            'NGINX_CUSTOM_CERTIFICATES_FOLDER' => '/tmp',
        ],
    ],
]);
