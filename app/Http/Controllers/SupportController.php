<?php
namespace App\Http\Controllers;
class SupportController {
    public function __invoke() { return response()->view('support',['email'=>config('shopify.support_email')]); }
}
