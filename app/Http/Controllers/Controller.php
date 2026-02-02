<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "BOMEQP Accreditation Management System API Documentation",
    title: "BOMEQP API"
)]
#[OA\Server(
    url: "https://app.bomeqp.com/api/api",
    description: "Production Server"
)]
#[OA\Server(
    url: "http://localhost:8000/api",
    description: "Local Development Server"
)]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter token in format: Bearer {token}"
)]
#[OA\Tag(
    name: "Authentication",
    description: "Authentication endpoints"
)]
#[OA\Tag(
    name: "Admin",
    description: "Group Admin endpoints"
)]
#[OA\Tag(
    name: "ACC",
    description: "ACC Admin endpoints"
)]
#[OA\Tag(
    name: "Training Center",
    description: "Training Center endpoints"
)]
#[OA\Tag(
    name: "Instructor",
    description: "Instructor endpoints"
)]
abstract class Controller
{
    //
}
