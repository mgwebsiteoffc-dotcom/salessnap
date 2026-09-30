<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class LegalPageController {
    public function __invoke(Request $request, string $page) {
        abort_unless(in_array($page,['privacy','terms'],true),404);
        return response()->view('legal-placeholder',['page'=>$page]);
    }
}
