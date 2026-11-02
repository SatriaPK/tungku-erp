<?php

namespace App\Actions\Documentation;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Route;

class DescribeApiContracts implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = $routeInfo->route->gatherMiddleware();
        $operation->security = [];
        if (in_array('auth:sanctum', $middleware, true)) {
            $operation->security = [new SecurityRequirement(['sessionCookie' => []])];
            $operation->addResponse($this->error(401, 'Unauthenticated.'));
            $operation->addResponse($this->error(403, 'Inactive account or operation not permitted.'));
        }
        foreach ($middleware as $name) {
            if (str_starts_with($name, 'throttle:')) {
                $operation->addResponse($this->error(429, 'Too many requests. Wait the Retry-After number of seconds before retrying.')
                    ->addHeader('Retry-After', new Header(description: 'Seconds until the current limit window resets.', schema: Schema::fromType(new IntegerType))));
                break;
            }
        }
    }

    public function document(OpenApi $api): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || ! in_array('PUT', $route->methods(), true) || ! in_array('PATCH', $route->methods(), true)) {
                continue;
            }
            foreach ($api->paths as $path) {
                if (trim($path->path, '/') !== substr($route->uri(), 4) || ! isset($path->operations['put']) || isset($path->operations['patch'])) {
                    continue;
                }
                $patch = clone $path->operations['put'];
                $patch->setMethod('patch')->setOperationId($patch->operationId.'.patch');
                $path->addOperation($patch);
            }
        }
    }

    private function error(int $status, string $description): Response
    {
        return Response::make($status)->setDescription($description)->setContent('application/json',
            Schema::fromType((new ObjectType)->addProperty('message', new StringType)->setRequired(['message'])));
    }
}
