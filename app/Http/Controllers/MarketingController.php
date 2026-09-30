<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketingController {
    public function __invoke(Request $request): Response {
        if ($request->filled('shop')) {
            return app(AppController::class)->__invoke($request);
        }
        $html = file_get_contents(public_path('marketing-site.html'));
        abort_unless($html !== false, 500, 'Marketing page is unavailable.');
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'public, max-age=300']);
    }
}
