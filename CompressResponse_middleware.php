<?php
// app/Http/Middleware/CompressResponse.php
// To use this file:
// 1. Create directory: Laravel/app/Http/Middleware/
// 2. Move this file to: Laravel/app/Http/Middleware/CompressResponse.php
// 3. Or run: copy "path\to\CompressResponse_standalone.php" "Laravel\app\Http\Middleware\CompressResponse.php"

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompressResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        if (config('compression.enabled') && 
            $request->header('Accept-Encoding') && 
            strpos($request->header('Accept-Encoding'), 'gzip') !== false) {
            
            if (strlen($response->getContent()) > config('compression.minimum_length')) {
                $response->setContent(gzencode($response->getContent(), config('compression.level')));
                $response->header('Content-Encoding', 'gzip');
            }
        }
        
        return $response;
    }
}
