<?php
require __DIR__ . '/bootstrap.php';

$groups = array(
    // OpenCart 3.0 routes
    'checkout/checkout' => 'checkout',
    'checkout/cart' => 'cart',
    'checkout/success' => 'order_confirmation',
    'checkout/failure' => 'order_confirmation',
    'checkout/confirm' => 'checkout',
    'extension/payment/pp_express' => 'checkout',
    'extension/payment/pp_express/checkout' => 'checkout',
    'account/login' => 'login',
    'account/forgotten' => 'login',
    'account/account' => 'account',
    'account/order/info' => 'account',
    'account/register' => 'account',
    'affiliate/login' => 'account',
    'product/product' => 'shop',
    'product/category' => 'shop',
    'product/search' => 'shop',
    'common/home' => 'shop',
    '' => 'shop',
    'information/contact' => 'other',
    'extension/module/proteside/cron' => 'other',
    // OpenCart 4.x routes (method after "." since 4.0.2, "|" before)
    'checkout/checkout.save' => 'checkout',
    'checkout/checkout|confirm' => 'checkout',
    'checkout/cart.add' => 'cart',
    'checkout/success.index' => 'order_confirmation',
    'extension/opencart/payment/cod.confirm' => 'checkout',
    'extension/mercadopago/payment/mercadopago' => 'checkout',
    'extension/opencart/module/account' => 'other',
    'account/login.login' => 'login',
    // Third-party one-page checkouts
    'checkout/simplecheckout' => 'checkout',
    'journal3/checkout' => 'checkout',
    'extension/quickcheckout/checkout' => 'checkout',
    'extension/d_quickcheckout/confirm' => 'checkout',
    'Checkout/Checkout' => 'checkout',
);
foreach ($groups as $route => $expected) {
    eq($expected, ProtesidePageMatcher::groupOf($route), "groupOf('$route')");
}

eq('checkout/checkout', ProtesidePageMatcher::normalizeRoute(' checkout/checkout.save '), 'normalizeRoute strips the method');
eq('common/home', ProtesidePageMatcher::normalizeRoute(''), 'empty route is home');
eq('checkout/checkout', ProtesidePageMatcher::normalizeRoute('checkout/checkout"<>'), 'odd characters dropped');

// Defaults: checkout + order confirmation only
$d = ProtesidePageMatcher::defaultPages();
ok(ProtesidePageMatcher::matches($d, array(), array('route' => 'checkout/checkout')), 'default: checkout');
ok(ProtesidePageMatcher::matches($d, array(), array('route' => 'checkout/success')), 'default: success');
ok(ProtesidePageMatcher::matches($d, array(), array('route' => 'extension/payment/stripe')), 'default: payment extension page');
ok(!ProtesidePageMatcher::matches($d, array(), array('route' => 'checkout/cart')), 'default: not cart');
ok(!ProtesidePageMatcher::matches($d, array(), array('route' => 'common/home')), 'default: not home');
ok(!ProtesidePageMatcher::matches($d, array(), array('route' => 'account/login')), 'default: not login');

$all = array_merge($d, array('all_store' => true));
ok(ProtesidePageMatcher::matches($all, array(), array('route' => 'information/contact')), 'entire store');
$cart = array_merge($d, array('cart' => true, 'checkout' => false));
ok(ProtesidePageMatcher::matches($cart, array(), array('route' => 'checkout/cart')), 'cart on');
ok(!ProtesidePageMatcher::matches($cart, array(), array('route' => 'checkout/checkout')), 'checkout off');
$login = array_merge($d, array('login' => true));
ok(ProtesidePageMatcher::matches($login, array(), array('route' => 'account/login')), 'login on');
ok(!ProtesidePageMatcher::matches($login, array(), array('route' => 'account/account')), 'login does not include my account');

// Custom patterns
$custom = ProtesidePageMatcher::normalizeCustom("  /finalizar-compra \r\n\n*route=extension/payment/mypay*\n/finalizar-compra\n");
eq(array('/finalizar-compra', '*route=extension/payment/mypay*'), $custom, 'normalizeCustom trims and de-duplicates');
$none = array_fill_keys(array_keys($d), false);
ok(ProtesidePageMatcher::matches($none, $custom, array('route' => 'information/x', 'path' => '/finalizar-compra/etapa-2', 'uri' => '/finalizar-compra/etapa-2')), 'substring pattern on path');
ok(ProtesidePageMatcher::matches($none, $custom, array('route' => '', 'path' => '/index.php', 'uri' => '/index.php?route=extension/payment/mypay/callback')), 'wildcard pattern on URI');
ok(!ProtesidePageMatcher::matches($none, $custom, array('route' => '', 'path' => '/blog', 'uri' => '/blog')), 'no match');
ok(ProtesidePageMatcher::matchesCustom(array('/pagamento/*'), '/PAGAMENTO/pix'), 'case-insensitive wildcard');
ok(!ProtesidePageMatcher::matchesCustom(array('/pagamento/*'), '/x/pagamento/pix'), 'wildcard is anchored');
ok(!ProtesidePageMatcher::matchesCustom(array('/a'), '', ''), 'empty request never matches');

// Request description
$r = ProtesidePageMatcher::describeRequest(array('route' => 'checkout/checkout'), array('REQUEST_URI' => '/index.php?route=checkout/checkout&x=1'));
eq(array('route' => 'checkout/checkout', 'path' => '/index.php', 'uri' => '/index.php?route=checkout/checkout&x=1'), $r, 'describeRequest');
$r = ProtesidePageMatcher::describeRequest(array('route' => array('x')), array());
eq('', $r['route'], 'non-string route ignored');

done('page_matcher');
