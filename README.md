# Laravel DynamoDB Setup with LocalStack

This project demonstrates how to set up a Laravel application to work with DynamoDB using LocalStack for local development and testing. Using DynamoDB with Laravel allows for a scalable, high-performance NoSQL database solution, while LocalStack enables local development without the need for an AWS account.

## Prerequisites

- Docker and Docker Compose
- PHP 8.3+
- Composer

## Setup Instructions

1. Clone this repository:
   ```
   git clone <repository-url>
   cd <project-directory>
   ```

2. Install dependencies:
   ```
   composer install
   ```

3. Update the `.env` file with the following variables:
   ```
   AWS_ACCESS_KEY_ID=fake
   AWS_SECRET_ACCESS_KEY=fake
   AWS_DEFAULT_REGION=us-east-1
   AWS_ENDPOINT=http://localstack:4566
   AWS_USE_PATH_STYLE_ENDPOINT=true
   DYNAMODB_CONNECTION=local
   ```
   These environment variables configure the AWS SDK to use LocalStack instead of actual AWS services.

4. Create `config/dynamodb.php`:
   ```php
   <?php
   return [
       'default' => env('DYNAMODB_CONNECTION', 'local'),
       'connections' => [
           'aws' => [
               'credentials' => [
                   'key' => env('AWS_ACCESS_KEY_ID'),
                   'secret' => env('AWS_SECRET_ACCESS_KEY'),
                   'token' => env('AWS_SESSION_TOKEN'),
               ],
               'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
               'debug' => env('DYNAMODB_DEBUG', false),
           ],
           'local' => [
               'credentials' => [
                   'key' => env('AWS_ACCESS_KEY_ID', 'fake'),
                   'secret' => env('AWS_SECRET_ACCESS_KEY', 'fake'),
               ],
               'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
               'endpoint' => env('AWS_ENDPOINT', 'http://localstack:4566'),
               'debug' => true,
           ],
       ],
   ];
   ```
   This configuration file sets up both AWS and local connections, allowing you to switch between them easily.

5. Create `app/Providers/DynamoDbServiceProvider.php`:
   ```php
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
   ```
   This service provider creates a singleton for the DynamoDbClient, ensuring that we use the same client throughout the application.

6. Update `app/Models/User.php`:
   ```php
   <?php
   namespace App\Models;

   use BaoPham\DynamoDb\DynamoDbModel;
   use Illuminate\Database\Eloquent\Concerns\HasUuids;

   class User extends DynamoDbModel
   {
       use HasUuids;

       protected $fillable = [
           'uuid', 'name', 'email',
       ];

       protected $hidden = [
           'password',
       ];

       protected $table = 'users';
       protected $primaryKey = 'uuid';
       public $incrementing = false;
       protected $keyType = 'string';

       protected $casts = [
           'email_verified_at' => 'datetime',
       ];
   }
   ```
   This model extends DynamoDbModel instead of the default Eloquent model, allowing it to interact with DynamoDB. We use UUIDs as primary keys because DynamoDB doesn't support auto-incrementing IDs.

7. Create `app/Http/Controllers/UserController.php`:
   ```php
   <?php
   namespace App\Http\Controllers;

   use App\Models\User;
   use Illuminate\Http\Request;
   use Illuminate\Support\Str;

   class UserController extends Controller
   {
       public function store(Request $request)
       {
           $attributes = $request->validate([
               'name' => 'required|string',
               'email' => 'required|email',
           ]);

           $attributes['uuid'] = (string) Str::uuid();

           $user = User::create($attributes);

           return response()->json(['message' => 'User created successfully', 'user' => $user]);
       }

       public function show($uuid)
       {
           $user = User::find($uuid);

           if (!$user) {
               return response()->json(['message' => 'User not found'], 404);
           }

           return response()->json($user);
       }

       public function index()
       {
           $users = User::all();

           return response()->json($users);
       }

       public function update(Request $request, $uuid)
       {
           $user = User::find($uuid);

           if (!$user) {
               return response()->json(['message' => 'User not found'], 404);
           }

           $attributes = $request->validate([
               'name' => 'sometimes|string',
               'email' => 'sometimes|email',
           ]);

           $user->update($attributes);

           return response()->json(['message' => 'User updated successfully', 'user' => $user]);
       }

       public function destroy($uuid)
       {
           $user = User::find($uuid);

           if (!$user) {
               return response()->json(['message' => 'User not found'], 404);
           }

           $user->delete();

           return response()->json(['message' => 'User deleted successfully']);
       }
   }
   ```
   This controller provides CRUD operations for the User model, demonstrating how to interact with DynamoDB through Laravel's model methods.

8. Update `routes/api.php`:
   ```php
   <?php
   use Illuminate\Support\Facades\Route;
   use App\Http\Controllers\UserController;

   Route::post('/users', [UserController::class, 'store']);
   Route::get('/users/{uuid}', [UserController::class, 'show']);
   Route::get('/users', [UserController::class, 'index']);
   Route::put('/users/{uuid}', [UserController::class, 'update']);
   Route::delete('/users/{uuid}', [UserController::class, 'destroy']);
   ```
   These routes define the API endpoints for user management.

9. Create `app/Console/Commands/CreateDynamoDbTable.php`:
   ```php
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
   ```
   This command allows you to create a DynamoDB table from the command line, which is useful for setting up your local development environment.

10. Update `bootstrap/providers.php` to register the DynamoDbServiceProvider:
    ```php
    <?php

    return [
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\DynamoDbServiceProvider::class,
    ];
    ```
    This ensures that our DynamoDbServiceProvider is loaded by Laravel.

11. Update `docker-compose.yml`:
    ```yaml
    services:
      laravel.test:
        build:
          context: './vendor/laravel/sail/runtimes/8.3'
          dockerfile: Dockerfile
          args:
            WWWGROUP: '${WWWGROUP}'
        image: 'sail-8.3/app'
        extra_hosts:
          - 'host.docker.internal:host-gateway'
        ports:
          - '${APP_PORT:-8080}:80'
          - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'
        environment:
          WWWUSER: '${WWWUSER}'
          LARAVEL_SAIL: 1
          XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
          XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
          IGNITION_LOCAL_SITES_PATH: '${PWD}'
        volumes:
          - '.:/var/www/html'
        networks:
          - sail
        depends_on:
          - localstack

      localstack:
        container_name: "${LOCALSTACK_DOCKER_NAME:-localstack-main}"
        image: localstack/localstack:latest
        ports:
          - "127.0.0.1:4566:4566"            # LocalStack Gateway
          - "127.0.0.1:4510-4559:4510-4559"  # external services port range
        environment:
          - DEBUG=${DEBUG:-0}
          - DOCKER_HOST=unix:///var/run/docker.sock
          - SERVICES=dynamodb
          - AWS_ACCESS_KEY_ID=fake
          - AWS_SECRET_ACCESS_KEY=fake
          - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION:-us-east-1}
        volumes:
          - "${LOCALSTACK_VOLUME_DIR:-./volume}:/var/lib/localstack"
          - "/var/run/docker.sock:/var/run/docker.sock"
        networks:
          - sail

      dynamodb-admin:
        image: aaronshaf/dynamodb-admin
        ports:
          - "8001:8001"
        environment:
          DYNAMO_ENDPOINT: "http://localstack:4566"
          AWS_REGION: "${AWS_DEFAULT_REGION:-us-east-1}"
          AWS_ACCESS_KEY_ID: "fake"
          AWS_SECRET_ACCESS_KEY: "fake"
        depends_on:
          - localstack
        networks:
          - sail

    networks:
      sail:
        driver: bridge

    volumes:
      sail-localstack:
        driver: local
    ```
    This docker-compose.yml file sets up the Laravel application, LocalStack for AWS services emulation, and DynamoDB Admin for a web-based interface to interact with DynamoDB.

## Running the Application

1. Pull the necessary Docker images:
   ```
   docker pull localstack/localstack:latest
   docker pull aaronshaf/dynamodb-admin
   ```
   This step ensures you have the latest versions of the required Docker images.

2. Start the Docker environment:
   ```
   ./vendor/bin/sail up -d
   ```

3. Create the DynamoDB table:
   ```
   ./vendor/bin/sail artisan dynamodb:create-table
   ```

4. The application should now be running and accessible at `http://localhost:8080`.

## Accessing DynamoDB Admin

After starting the Docker environment, you can access the DynamoDB Admin interface at:

```
http://localhost:8001
```

This provides a web-based GUI for interacting with your local DynamoDB instance.

## API Endpoints and Usage Examples

Here are the API endpoints and examples of how to use them with curl:

1. Create a user:
   - Endpoint: POST /api/users
   - Example:
     ```
     curl -X POST http://localhost:8080/api/users \
     -H "Content-Type: application/json" \
     -d '{"name": "John Doe", "email": "john@example.com"}'
     ```

2. Get all users:
   - Endpoint: GET /api/users
   - Example:
     ```
     curl http://localhost:8080/api/users
     ```

3. Get a specific user:
   - Endpoint: GET /api/users/{uuid}
   - Example (replace {uuid} with an actual UUID):
     ```
     curl http://localhost:8080/api/users/{uuid}
     ```

4. Update a user:
   - Endpoint: PUT /api/users/{uuid}
   - Example (replace {uuid} with an actual UUID):
     ```
     curl -X PUT http://localhost:8080/api/users/{uuid} \
     -H "Content-Type: application/json" \
     -d '{"name": "John Updated", "email": "john.updated@example.com"}'
     ```

5. Delete a user:
   - Endpoint: DELETE /api/users/{uuid}
   - Example (replace {uuid} with an actual UUID):
     ```
     curl -X DELETE http://localhost:8080/api/users/{uuid}
     ```

Remember to replace `{uuid}` with the actual UUID of a user when making requests to specific user endpoints.

## Interacting with DynamoDB

To interact with DynamoDB directly, you can use the AWS CLI configured to use the LocalStack endpoint:

```
aws dynamodb list-tables --endpoint-url http://localhost:4566
```

This setup allows you to develop and test your Laravel application with DynamoDB locally, without incurring AWS costs or requiring an internet connection. It provides a realistic environment for development and testing, closely mimicking the behavior of a production DynamoDB setup.

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [DynamoDB Documentation](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Introduction.html)
- [LocalStack Documentation](https://docs.localstack.cloud/overview/)
