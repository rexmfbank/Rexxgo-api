<?php

namespace App\Swagger;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Rexxgo API",
 *     description="API documentation for Auth and Wallet modules"
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="Base server"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication and onboarding"
 * )
 *
 * @OA\Tag(
 *     name="Wallet",
 *     description="Wallet operations"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class OpenApi
{
    // This class only holds OpenAPI annotations.
}


