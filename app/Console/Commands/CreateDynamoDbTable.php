<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aws\DynamoDb\DynamoDbClient;

class CreateDynamoDbTable extends Command
{
    protected $signature = 'dynamodb:create-table {--table=users}';
    protected $description = 'Create a table in DynamoDB';

    public function handle()
    {
        $tableName = $this->option('table');
        $client = app(DynamoDbClient::class);

        try {
            $result = $client->createTable([
                'TableName' => $tableName,
                'AttributeDefinitions' => [
                    [
                        'AttributeName' => 'uuid',
                        'AttributeType' => 'S'
                    ],
                ],
                'KeySchema' => [
                    [
                        'AttributeName' => 'uuid',
                        'KeyType' => 'HASH'
                    ],
                ],
                'ProvisionedThroughput' => [
                    'ReadCapacityUnits' => 5,
                    'WriteCapacityUnits' => 5,
                ],
            ]);

            $this->info("Table '{$tableName}' created successfully: " . $result['TableDescription']['TableName']);
        } catch (\Exception $e) {
            $this->error("Error creating table '{$tableName}': " . $e->getMessage());
        }
    }
}