<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');
$userId=(int)current_user()['id'];

if(is_post()){
    verify_csrf_or_abort();
    $action=(string)($_POST['action']??'');
    if($action==='read'){
        $id=(int)($_POST['notification_id']??0);
        if($id>0) customer_mark_notification_read($userId,$id);
    } elseif($action==='open'){
        $id=(int)($_POST['notification_id']??0);
        $notice=$id>0?customer_notification($userId,$id):null;
        if($notice){
            customer_mark_notification_read($userId,$id);
            $target=trim((string)($notice['target_url']??''));
            if($target!=='' && !preg_match('#^https?://#i',$target) && !str_contains($target,'..')) redirect($target);
        }
    } elseif($action==='read_all'){
        customer_mark_all_notifications_read($userId);
        flash('success','All notifications marked as read.');
    }
    redirect('customer/notifications.php');
}

$notifications=customer_notifications($userId,100);
$unread=customer_unread_notification_count($userId);
$pageTitle='Notifications';
$success=flash('success');
require __DIR__.'/../includes/header.php';
?>
<div class="portal-shell"><?php require __DIR__.'/../includes/sidebar.php';?><main class="portal-main customer-portal-main">
<?php if($success):?><div class="alert alert-success"><?= e($success) ?></div><?php endif;?>
<div class="portal-page-header"><div><span class="eyebrow">ACCOUNT UPDATES</span><h1 class="mt-2">Notifications</h1><p><?= $unread ?> unread update<?= $unread===1?'':'s' ?> about your orders and account.</p></div><?php if($unread>0):?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-outline-coffee" type="submit">Mark all as read</button></form><?php endif;?></div>
<?php if(!$notifications):?><div class="empty-state"><i class="bi bi-bell"></i><h2>No notifications yet.</h2><p>Order and payment updates will appear here.</p></div><?php else:?><div class="notification-list"><?php foreach($notifications as $notice):?><article class="notification-card <?= empty($notice['read_at'])?'unread':'' ?>"><div class="notification-icon"><i class="bi <?= empty($notice['read_at'])?'bi-bell-fill':'bi-bell' ?>"></i></div><div class="notification-content"><div class="d-flex flex-wrap gap-2 align-items-center"><strong><?= e($notice['title']) ?></strong><?php if(empty($notice['read_at'])):?><span class="notification-new">New</span><?php endif;?></div><p><?= e($notice['message']) ?></p><small><?= e(customer_datetime($notice['created_at'])) ?></small></div><div class="notification-actions"><?php if(!empty($notice['target_url'])):?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="open"><input type="hidden" name="notification_id" value="<?= (int)$notice['id'] ?>"><button class="btn btn-sm btn-outline-coffee" type="submit">Open</button></form><?php endif;?><?php if(empty($notice['read_at'])):?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="notification_id" value="<?= (int)$notice['id'] ?>"><button class="btn btn-sm btn-link text-muted" type="submit">Mark read</button></form><?php endif;?></div></article><?php endforeach;?></div><?php endif;?>
</main></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
