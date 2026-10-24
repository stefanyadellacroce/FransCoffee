<?php

declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if(!is_logged_in()||user_role()!=='CUSTOMER'){http_response_code(401);echo json_encode(['ok'=>false,'message'=>'Sign in as a customer to use a promo code.']);exit;}
$cart=cart_items();$repriced=checkout_reprice_cart($cart);if(!$repriced['ok']){echo json_encode(['ok'=>false,'message'=>$repriced['error']]);exit;}
$r=promotion_validate((string)($_GET['code']??''),(float)$repriced['subtotal'],(int)current_user()['id']);
echo json_encode(['ok'=>$r['ok'],'message'=>$r['message'],'code'=>$r['promotion']['code']??null,'discount'=>$r['discount']??0,'subtotal'=>$repriced['subtotal']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
