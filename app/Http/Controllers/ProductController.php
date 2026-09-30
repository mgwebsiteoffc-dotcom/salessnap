<?php
namespace App\Http\Controllers;

use App\Services\ShopifyGraphql;
use Illuminate\Http\Request;

class ProductController {
    public function index(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');
        $collectionId = (string) $request->query('collection_id', '');

        if ($collectionId !== '') {
            return response()->json([
                'products' => $graphql->productsByCollection($shop, $collectionId),
            ]);
        }

        $term = mb_substr(trim((string)$request->query('q', '')), 0, 80);
        return response()->json([
            'products' => $graphql->searchProducts($shop, $term),
        ]);
    }

    public function collections(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');
        $term = mb_substr(trim((string)$request->query('q', '')), 0, 80);
        return response()->json([
            'collections' => $graphql->searchCollections($shop, $term),
        ]);
    }
}
