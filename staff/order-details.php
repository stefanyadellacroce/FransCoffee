<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['STAFF','ADMIN']);

$orderNumber = trim((string)($_GET['order'] ?? $_POST['order_number'] ?? ''));
$order = $orderNumber !== '' ? ops_order_detail($orderNumber) : null;
if (!$order) {
    http_response_code(404); $pageTitle='Order not found'; require __DIR__.'/../includes/header.php';
    ?><div class="portal-shell"><?php require __DIR__.'/../includes/sidebar.php'; ?><main class="portal-main"><div class="empty-state"><i class="bi bi-receipt"></i><h1>Order not found.</h1><p>The requested order does not exist.</p><a class="btn btn-coffee" href="<?= url('staff/orders.php') ?>">Back to orders</a></div></main></div><?php require __DIR__.'/../includes/footer.php'; exit;
}

if (is_post()) {
    verify_csrf_or_abort();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update_status') {
        $result = ops_update_order_status((int)$order['id'], (string)($_POST['status'] ?? ''), (int)current_user()['id'], (string)($_POST['note'] ?? ''));
    } elseif ($action === 'review_payment') {
        $result = ops_review_payment((int)$order['payment_id'], (string)($_POST['decision'] ?? ''), (int)current_user()['id'], (string)($_POST['payment_note'] ?? ''));
    } else {
        $result = ['ok'=>false,'message'=>'Choose a valid action.'];
    }
    flash($result['ok']?'success':'error',$result['message']);
    redirect('staff/order-details.php?order='.rawurlencode($orderNumber));
}

$order = ops_order_detail($orderNumber);
$allowedTransitions = ops_allowed_transitions($order);
$pageTitle='Manage '.$order['order_number'];
$bodyClass='portal-page';
require __DIR__.'/../includes/header.php';
$success=flash('success'); $error=flash('error'); $address=$order['delivery_address'];
?>
<div class="portal-shell"><?php require __DIR__.'/../includes/sidebar.php'; ?><main class="portal-main order-ops-main">
<header class="portal-page-header"><div><a class="portal-back-link" href="<?= url('staff/orders.php') ?>"><i class="bi bi-arrow-left"></i> Orders</a><h1 class="mt-2 mb-1"><?= e((string)$order['order_number']) ?></h1><p class="text-muted mb-0"><?= e((string)$order['order_source']) ?> · <?= e(ucfirst(strtolower((string)$order['fulfillment_type']))) ?> · placed <?= e(date('M j, Y g:i A',strtotime((string)$order['placed_at']))) ?></p></div><div class="text-lg-end"><span class="badge <?= e(status_badge_class((string)$order['status'])) ?> order-status-badge"><?= e(ops_status_label((string)$order['status'])) ?></span><div class="fs-4 fw-bold mt-2"><?= money((float)$order['total_amount']) ?></div></div></header>
<?php if($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>

<div class="row g-4 align-items-start"><div class="col-xl-8">
<section class="portal-card"><div class="panel-heading"><div><span class="eyebrow">ORDER ITEMS</span><h2>Items and customizations</h2></div><span class="text-muted small"><?= count($order['items']) ?> line<?= count($order['items'])===1?'':'s' ?></span></div>
<div class="ops-order-items"><?php foreach($order['items'] as $item): ?><div class="ops-order-item"><div><strong><?= e((string)$item['product_name']) ?> × <?= (int)$item['quantity'] ?></strong><?php if($item['options']): ?><small><?= e(implode(' · ',array_map(fn($opt)=>$opt['option_name'].': '.$opt['option_value'],$item['options']))) ?></small><?php endif; ?></div><div class="text-end"><span><?= money((float)$item['unit_price']) ?> each</span><strong><?= money((float)$item['line_total']) ?></strong></div></div><?php endforeach; ?></div>
</section>

<section class="portal-card mt-4"><div class="panel-heading"><div><span class="eyebrow">CUSTOMER & FULFILLMENT</span><h2><?= e((string)($order['contact_name'] ?: $order['account_name'] ?: 'Walk-in Customer')) ?></h2></div></div>
<div class="ops-info-grid"><div><span>Email</span><strong><?= e((string)($order['contact_email'] ?: $order['account_email'] ?: '—')) ?></strong></div><div><span>Phone</span><strong><?= e((string)($order['contact_phone'] ?: '—')) ?></strong></div><div><span>Fulfillment</span><strong><?= e(ucfirst(strtolower((string)$order['fulfillment_type']))) ?></strong></div><div><span>Source</span><strong><?= e((string)$order['order_source']) ?></strong></div></div>
<?php if($order['fulfillment_type']==='DELIVERY' && $address): ?><div class="address-display mt-3"><i class="bi bi-geo-alt"></i><div><strong><?= e((string)($address['label']??'Delivery address')) ?></strong><span><?= e(trim(($address['street']??'').', '.($address['barangay']??'').', '.($address['city']??'').', '.($address['province']??''),', ')) ?><?= !empty($address['postal_code'])?' '.e((string)$address['postal_code']):'' ?></span><?php if(!empty($address['landmark'])):?><small>Landmark: <?= e((string)$address['landmark']) ?></small><?php endif;?></div></div><?php endif; ?>
<?php if(!empty($order['notes'])): ?><div class="ops-note mt-3"><i class="bi bi-chat-left-text"></i><div><strong>Order note</strong><span><?= e((string)$order['notes']) ?></span></div></div><?php endif; ?>
</section>

<section class="portal-card mt-4"><div class="panel-heading"><div><span class="eyebrow">ORDER TIMELINE</span><h2>Status history</h2></div></div><div class="order-timeline"><?php foreach($order['history'] as $index=>$event): ?><div class="timeline-event"><span class="timeline-dot <?= $index===count($order['history'])-1?'current':'' ?>"><i class="bi bi-check2"></i></span><div><strong><?= e(ops_status_label((string)$event['status'])) ?></strong><small><?= e(date('M j, Y g:i A',strtotime((string)$event['created_at']))) ?><?= !empty($event['changed_by_name'])?' · '.e((string)$event['changed_by_name']):'' ?><?= !empty($event['note'])?' · '.e((string)$event['note']):'' ?></small></div></div><?php endforeach; ?></div></section>
</div>

<div class="col-xl-4">
<aside class="portal-card ops-action-card"><span class="eyebrow">PAYMENT</span><div class="ops-payment-head"><div><h2><?= e((string)($order['payment_method'] ?: 'No payment method')) ?></h2><small><?= e((string)($order['payment_number'] ?: '')) ?></small></div><span class="badge <?= e(status_badge_class((string)$order['payment_status'])) ?>"><?= e((string)$order['payment_status']) ?></span></div>
<div class="profile-summary-row"><span>Amount</span><strong><?= money((float)$order['payment_amount']) ?></strong></div><?php if(!empty($order['reference_number'])): ?><div class="profile-summary-row"><span>Reference</span><strong><?= e((string)$order['reference_number']) ?></strong></div><?php endif; ?><?php if(!empty($order['verified_at'])): ?><div class="profile-summary-row"><span>Verified by</span><strong><?= e((string)($order['verifier_name'] ?: 'Staff')) ?></strong></div><?php endif; ?>
<?php if(!empty($order['verification_note'])): ?><div class="ops-note mt-3"><i class="bi bi-journal-text"></i><div><strong>Verification note</strong><span><?= e((string)$order['verification_note']) ?></span></div></div><?php endif; ?>
<?php if(!empty($order['payment_proof'])): ?><div class="mt-3"><small class="text-muted d-block mb-2">Submitted payment proof</small><a href="<?= url((string)$order['payment_proof']['file_path']) ?>" target="_blank" rel="noopener"><img class="payment-proof-thumb ops-proof" src="<?= url((string)$order['payment_proof']['file_path']) ?>" alt="Payment proof for <?= e((string)$order['order_number']) ?>"></a></div><?php endif; ?>
<?php if($order['payment_status']==='PENDING'): ?><form method="post" class="mt-3 ops-review-form"><?= csrf_field() ?><input type="hidden" name="order_number" value="<?= e((string)$order['order_number']) ?>"><input type="hidden" name="action" value="review_payment"><label class="form-label" for="paymentNote">Review note <span class="text-muted">(optional)</span></label><textarea class="form-control" id="paymentNote" name="payment_note" rows="2" maxlength="255" placeholder="e.g. Reference and proof verified"></textarea><div class="d-grid gap-2 mt-2"><button class="btn btn-success" name="decision" value="APPROVE" type="submit"><i class="bi bi-check-circle me-2"></i>Approve payment</button><button class="btn btn-outline-danger" name="decision" value="REJECT" type="submit"><i class="bi bi-x-circle me-2"></i>Reject payment</button></div></form><?php elseif($order['payment_status']==='UNPAID' && (int)$order['is_online']===0): ?><form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="order_number" value="<?= e((string)$order['order_number']) ?>"><input type="hidden" name="action" value="review_payment"><input type="hidden" name="decision" value="MARK_PAID"><label class="form-label" for="cashNote">Collection note <span class="text-muted">(optional)</span></label><input class="form-control" id="cashNote" name="payment_note" maxlength="255" placeholder="e.g. Cash received at pickup"><button class="btn btn-coffee w-100 mt-2" type="submit"><i class="bi bi-cash-coin me-2"></i>Mark payment paid</button></form><?php endif; ?>
</aside>

<aside class="portal-card ops-action-card mt-4"><span class="eyebrow">ORDER STATUS</span><h2>Move this order</h2><?php if($allowedTransitions): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="order_number" value="<?= e((string)$order['order_number']) ?>"><input type="hidden" name="action" value="update_status"><label class="form-label" for="nextStatus">Next status</label><select class="form-select" id="nextStatus" name="status" required><?php foreach($allowedTransitions as $next): ?><option value="<?= e($next) ?>"><?= e(ops_status_label($next)) ?></option><?php endforeach; ?></select><label class="form-label mt-3" for="statusNote">Status note <span class="text-muted">(optional)</span></label><textarea class="form-control" id="statusNote" name="note" maxlength="255" rows="2" placeholder="Visible in order history"></textarea><button class="btn btn-coffee w-100 mt-3" type="submit"><i class="bi bi-arrow-right-circle me-2"></i>Update order</button></form><?php else: ?><div class="ops-terminal-state"><i class="bi bi-check2-circle"></i><strong><?= e(ops_status_label((string)$order['status'])) ?></strong><span>This order has no further workflow actions.</span></div><?php endif; ?></aside>

<aside class="portal-card mt-4"><span class="eyebrow">TOTALS</span><div class="profile-summary-row mt-2"><span>Subtotal</span><strong><?= money((float)$order['subtotal']) ?></strong></div><div class="profile-summary-row"><span>Discount</span><strong>-<?= money((float)$order['discount_amount']) ?></strong></div><div class="profile-summary-row"><span>Delivery</span><strong><?= money((float)$order['delivery_fee']) ?></strong></div><div class="summary-total"><span>Total</span><strong><?= money((float)$order['total_amount']) ?></strong></div></aside>
</div></div>
</main></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
