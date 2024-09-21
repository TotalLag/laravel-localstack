<?php

namespace App\Providers;

use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Support\ServiceProvider;

class DynamoDbServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(DynamoDbClient::class, function ($app) {
            $config = config('dynamodb.connections.' . config('dynamodb.default'));
            
            $clientConfig = [
                'region'   => $config['region'],
                'version'  => 'latest',
                'endpoint' => $config['endpoint'] ?? null,
                'credentials' => [
                    'key'    => $config['credentials']['key'],
                    'secret' => $config['credentials']['secret'],
                ],
            ];

            // Add use_path_style_endpoint if it's set in the config
            if (isset($config['use_path_style_endpoint'])) {
                $clientConfig['use_path_style_endpoint'] = $config['use_path_style_endpoint'];
            }

            return new DynamoDbClient($clientConfig);
        });
    }

    public function boot()
    {
        //
    }
}