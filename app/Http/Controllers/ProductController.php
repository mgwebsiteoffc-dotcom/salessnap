<?php
namespace App\Http\Controllers;
use App\Services\ShopifyGraphql;
use Illuminate\Http\Request;
class ProductController {
    public function index(Request $request, ShopifyGraphql $graphql) {
        $term=mb_substr(trim((string)$request->query('q','')),0,80);
        return response()->json(['products'=>$graphql->searchProducts($request->attributes->get('shop'),$term)]);
    }
}
