<?php

declare(strict_types=1);

namespace Tobion\OpenApiSymfonyRouting\Tests\Fixtures\SeveralRoutesOnOneAction;

use Swagger\Annotations as SWG;

/**
 * @SWG\Swagger(
 *     @SWG\Info(title="My API", version="1.0")
 * )
 */
class Controller
{
    /**
     * @SWG\Get(
     *     path="/foobar",
     *     @SWG\Response(response="200", description="Success")
     * )
     *
     * @SWG\Post(
     *     path="/foobar",
     *     @SWG\Response(response="200", description="Success")
     * )
     *
     * @SWG\Get(
     *     path="/foo-bar",
     *     operationId="my-name",
     *     @SWG\Response(response="200", description="Success")
     * )
     */
    public function __invoke(): void
    {
    }
}
