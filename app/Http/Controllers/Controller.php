<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "BOMEQP API Documentation",
    description: "Comprehensive API documentation for BOMEQP Accreditation Management System"
)]
#[OA\Server(
    url: "/api",
    description: "API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    name: "Authorization",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter token in format: Bearer {token}"
)]
abstract class Controller
{
    //
}
