<?php
namespace Tests\Feature;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ShopifySessionTokenTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();config(['shopify.api_key'=>'test-client-id','shopify.api_secret'=>'test-secret']);Shop::create(['shop_domain'=>'demo.myshopify.com','access_token'=>'shpat_test','granted_scopes'=>'read_products,write_products','installed_at'=>now()]);}
    public function test_embedded_api_rejects_request_without_a_session_token(): void {$this->getJson('/api/dashboard')->assertUnauthorized();}
    public function test_signed_current_shop_session_token_is_accepted(): void {$this->withHeader('Authorization','Bearer '.$this->jwt())->getJson('/api/dashboard')->assertOk()->assertJsonPath('shop','demo.myshopify.com');}
    public function test_invalid_session_token_signature_is_rejected(): void {$jwt=$this->jwt();$parts=explode('.',$jwt);$parts[2]=$this->b64('not-a-valid-signature');$this->withHeader('Authorization','Bearer '.implode('.',$parts))->getJson('/api/dashboard')->assertUnauthorized();}
    private function jwt(): string {$now=time();$head=$this->b64(json_encode(['alg'=>'HS256','typ'=>'JWT']));$body=$this->b64(json_encode(['iss'=>'https://demo.myshopify.com/admin','dest'=>'https://demo.myshopify.com','aud'=>'test-client-id','sub'=>'123','iat'=>$now,'nbf'=>$now,'exp'=>$now+60,'jti'=>'test-jti']));$sig=$this->b64(hash_hmac('sha256',$head.'.'.$body,'test-secret',true));return $head.'.'.$body.'.'.$sig;}
    private function b64(string $s): string {return rtrim(strtr(base64_encode($s),'+/','-_'),'=');}
}
