<?php

namespace App\Http\Controllers;
use OpenApi\Attributes as OA;


#[OA\Info(
    version: "1.0.0",
    title: "Nusantara Booking API",
    description: "Official API Documentation"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    name: "Authorization",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Masukkan Token JWT hasil dari endpoint /api/login"
)]


abstract class Controller
{
    //
}
