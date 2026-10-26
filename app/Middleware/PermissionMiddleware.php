<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpException;
use App\Http\Request;

final class PermissionMiddleware
{
    public function require(Request $request, string $permission): void
    {
        if (!$request->can($permission)) {
            throw HttpException::forbidden();
        }
    }
}
