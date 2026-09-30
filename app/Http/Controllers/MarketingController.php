<?php
namespace App\Http\Controllers;
use Illuminate\Http\Response;
class MarketingController {
    public function __invoke(): Response {
        $html=file_get_contents(public_path('marketing-site.html'));
        abort_unless($html!==false,500,'Marketing page is unavailable.');
        return response($html,200,['Content-Type'=>'text/html; charset=UTF-8','Cache-Control'=>'public, max-age=300']);
    }
}
