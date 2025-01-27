<?php

namespace Brickhouse\Http\Controllers;

use Brickhouse\Http\Controller;
use Brickhouse\Http\HttpStatus;
use Brickhouse\Http\Response;
use Brickhouse\Http\Transport\ContentType;

class StaticResourceController extends Controller
{
    public function __invoke(): Response
    {
        $path = $this->request->path();
        $absolutePath = public_path($path);

        if (file_exists($absolutePath) && is_file($absolutePath)) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mimetype = match ($extension) {
                "js" => ContentType::JS,
                "json" => ContentType::JSON,
                "css" => ContentType::CSS,
                default => ContentType::TXT,
            };

            return Response::text(file_get_contents($absolutePath), $mimetype);
        }

        return Response::new(status: HttpStatus::NOT_FOUND);
    }
}
