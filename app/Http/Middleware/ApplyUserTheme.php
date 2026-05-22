<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserTheme
{
    /**
     * Inject a small inline script that applies the correct theme class
     * to <html> before paint, avoiding flash of unstyled content.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isSuccessful()) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $theme = $this->resolveTheme($request);

        $script = <<<JS
<script>
    (function() {
        var theme = '{$theme}';
        if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>
JS;

        $content = $response->getContent();
        if ($content === false) {
            return $response;
        }

        // Inject script immediately after <head> to run before paint
        $content = preg_replace('/(<head[^>]*>)/i', '$1'.$script, $content, 1);
        $response->setContent($content);

        return $response;
    }

    private function resolveTheme(Request $request): string
    {
        $user = $request->user();

        if ($user && in_array($user->theme, [User::THEME_LIGHT, User::THEME_DARK], true)) {
            return $user->theme;
        }

        return User::THEME_SYSTEM;
    }
}
