<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add the browser security headers to every response. A header the controller already set is kept.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers.enabled')) {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        if (app()->isProduction() && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.config('security.headers.hsts_max_age');
        }

        if (config('security.csp.enabled')) {
            $headers['Content-Security-Policy-Report-Only'] = $this->contentSecurityPolicy();
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        return collect(config('security.csp.directives'))
            ->map(fn (string $sources, string $directive): string => "{$directive} {$sources}")
            ->implode('; ');
    }
}
