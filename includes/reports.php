<?php

declare(strict_types=1);

function report_valid_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

function report_date_range(array $input): array
{
    $today = new DateTimeImmutable('today');
    $defaultFrom = $today->modify('first day of this month')->format('Y-m-d');
    $defaultTo = $today->format('Y-m-d');
    $from = trim((string)($input['from'] ?? $defaultFrom));
    $to = trim((string)($input['to'] ?? $defaultTo));
    if (!report_valid_date($from)) $from = $defaultFrom;
    if (!report_valid_date($to)) $to = $defaultTo;
    if ($from > $to) [$from, $to] = [$to, $from];
    return ['from'=>$from,'to'=>$to,'from_dt'=>$from.' 00:00:00','to_dt'=>$to.' 23:59:59'];
}

function report_period_label(array $range): string
{
    $a = new DateTimeImmutable($range['from']);
    $b = new DateTimeImmutable($range['to']);
    if ($range['from'] === $range['to']) return $a->format('F j, Y');
    return $a->format('M j, Y').' – '.$b->format('M j, Y');
}

function report_sales(array $range): array
{
    $pdo=db();
    $stmt=$pdo->prepare("SELECT
        COUNT(*) payment_count,
        COALESCE(SUM(p.amount),0) revenue,
        COALESCE(AVG(p.amount),0) average_sale,
        COALESCE(SUM(o.order_source='ONLINE'),0) online_count,
        COALESCE(SUM(o.order_source='POS'),0) pos_count
        FROM payments p JOIN orders o ON o.id=p.order_id
        WHERE p.status='PAID' AND COALESCE(p.verified_at,p.updated_at) BETWEEN :from_dt AND :to_dt");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    $summary=$stmt->fetch()?:[];

    $stmt=$pdo->prepare("SELECT DATE(COALESCE(p.verified_at,p.updated_at)) sale_date,COUNT(*) transactions,
        COALESCE(SUM(p.amount),0) revenue,
        COALESCE(SUM(CASE WHEN o.order_source='ONLINE' THEN p.amount ELSE 0 END),0) online_revenue,
        COALESCE(SUM(CASE WHEN o.order_source='POS' THEN p.amount ELSE 0 END),0) pos_revenue
        FROM payments p JOIN orders o ON o.id=p.order_id
        WHERE p.status='PAID' AND COALESCE(p.verified_at,p.updated_at) BETWEEN :from_dt AND :to_dt
        GROUP BY DATE(COALESCE(p.verified_at,p.updated_at)) ORDER BY sale_date");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    $daily=$stmt->fetchAll();

    $stmt=$pdo->prepare("SELECT pm.name method_name,pm.code,COUNT(*) transactions,COALESCE(SUM(p.amount),0) amount
        FROM payments p JOIN payment_methods pm ON pm.id=p.payment_method_id
        WHERE p.status='PAID' AND COALESCE(p.verified_at,p.updated_at) BETWEEN :from_dt AND :to_dt
        GROUP BY pm.id,pm.name,pm.code ORDER BY amount DESC");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    $methods=$stmt->fetchAll();

    $stmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE expense_date BETWEEN :from_date AND :to_date");
    $stmt->execute(['from_date'=>$range['from'],'to_date'=>$range['to']]);
    $expenses=(float)$stmt->fetchColumn();
    return ['summary'=>[
        'payment_count'=>(int)($summary['payment_count']??0),
        'revenue'=>(float)($summary['revenue']??0),
        'average_sale'=>(float)($summary['average_sale']??0),
        'online_count'=>(int)($summary['online_count']??0),
        'pos_count'=>(int)($summary['pos_count']??0),
        'expenses'=>$expenses,
        'net'=>(float)($summary['revenue']??0)-$expenses,
    ],'daily'=>$daily,'methods'=>$methods];
}

function report_orders(array $range): array
{
    $pdo=db();
    $stmt=$pdo->prepare("SELECT COUNT(*) total,
        COALESCE(SUM(status='COMPLETED'),0) completed,
        COALESCE(SUM(status='CANCELLED'),0) cancelled,
        COALESCE(SUM(order_source='ONLINE'),0) online_count,
        COALESCE(SUM(order_source='POS'),0) pos_count,
        COALESCE(SUM(fulfillment_type='PICKUP'),0) pickup_count,
        COALESCE(SUM(fulfillment_type='DELIVERY'),0) delivery_count,
        COALESCE(SUM(CASE WHEN status<>'CANCELLED' THEN total_amount ELSE 0 END),0) order_value
        FROM orders WHERE placed_at BETWEEN :from_dt AND :to_dt");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    $summary=$stmt->fetch()?:[];
    $stmt=$pdo->prepare("SELECT o.*,p.status payment_status,pm.name payment_method
        FROM orders o LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id
        WHERE o.placed_at BETWEEN :from_dt AND :to_dt ORDER BY o.placed_at DESC,o.id DESC LIMIT 1000");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    $rows=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT status,COUNT(*) count FROM orders WHERE placed_at BETWEEN :from_dt AND :to_dt GROUP BY status ORDER BY count DESC");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    return ['summary'=>$summary,'rows'=>$rows,'status_breakdown'=>$stmt->fetchAll()];
}

function report_inventory(array $range): array
{
    $pdo=db();
    $summary=$pdo->query("SELECT COUNT(*) total_items,COALESCE(SUM(status='ACTIVE'),0) active_items,
        COALESCE(SUM(status='ACTIVE' AND quantity<=reorder_level),0) low_stock,
        COALESCE(SUM(CASE WHEN status='ACTIVE' THEN quantity*unit_cost ELSE 0 END),0) stock_value
        FROM inventory_items")->fetch()?:[];
    $stmt=$pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN type='STOCK_IN' THEN quantity ELSE 0 END),0) stock_in,
        COALESCE(SUM(CASE WHEN type='STOCK_OUT' THEN quantity ELSE 0 END),0) stock_out,
        COALESCE(SUM(type='WASTE'),0) waste_count,
        COUNT(*) movements
        FROM inventory_transactions WHERE created_at BETWEEN :from_dt AND :to_dt");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    $movements=$stmt->fetch()?:[];
    $items=$pdo->query("SELECT i.*,s.name supplier_name FROM inventory_items i LEFT JOIN suppliers s ON s.id=i.supplier_id ORDER BY (i.status='ACTIVE' AND i.quantity<=i.reorder_level) DESC,i.item_name")->fetchAll();
    $stmt=$pdo->prepare("SELECT t.*,i.item_code,i.item_name,i.unit,u.first_name,u.last_name
        FROM inventory_transactions t JOIN inventory_items i ON i.id=t.inventory_item_id LEFT JOIN users u ON u.id=t.created_by
        WHERE t.created_at BETWEEN :from_dt AND :to_dt ORDER BY t.created_at DESC,t.id DESC LIMIT 1000");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    return ['summary'=>$summary,'movements'=>$movements,'items'=>$items,'transactions'=>$stmt->fetchAll()];
}

function report_payments(array $range): array
{
    $pdo=db();
    $stmt=$pdo->prepare("SELECT COUNT(*) total,
        COALESCE(SUM(status='PAID'),0) paid_count,COALESCE(SUM(status='PENDING'),0) pending_count,
        COALESCE(SUM(status='UNPAID'),0) unpaid_count,COALESCE(SUM(status='FAILED'),0) failed_count,
        COALESCE(SUM(status='REFUNDED'),0) refunded_count,
        COALESCE(SUM(CASE WHEN status='PAID' THEN amount ELSE 0 END),0) paid_amount,
        COALESCE(SUM(CASE WHEN status='REFUNDED' THEN amount ELSE 0 END),0) refunded_amount
        FROM payments WHERE created_at BETWEEN :from_dt AND :to_dt");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);$summary=$stmt->fetch()?:[];
    $stmt=$pdo->prepare("SELECT p.*,o.order_number,o.contact_name,pm.code method_code,pm.name method_name,u.first_name verifier_first,u.last_name verifier_last
        FROM payments p JOIN orders o ON o.id=p.order_id JOIN payment_methods pm ON pm.id=p.payment_method_id LEFT JOIN users u ON u.id=p.verified_by
        WHERE p.created_at BETWEEN :from_dt AND :to_dt ORDER BY p.created_at DESC,p.id DESC LIMIT 1000");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);$rows=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT pm.name method_name,pm.code,COUNT(*) count,COALESCE(SUM(CASE WHEN p.status='PAID' THEN p.amount ELSE 0 END),0) paid_amount
        FROM payments p JOIN payment_methods pm ON pm.id=p.payment_method_id WHERE p.created_at BETWEEN :from_dt AND :to_dt GROUP BY pm.id,pm.name,pm.code ORDER BY paid_amount DESC,count DESC");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);
    return ['summary'=>$summary,'rows'=>$rows,'methods'=>$stmt->fetchAll()];
}

function report_products(array $range): array
{
    $pdo=db();
    $stmt=$pdo->prepare("SELECT oi.product_id,oi.product_name,SUM(oi.quantity) quantity_sold,COALESCE(SUM(oi.line_total),0) revenue,
        COUNT(DISTINCT oi.order_id) order_count,COALESCE(AVG(oi.unit_price),0) avg_unit_price
        FROM order_items oi JOIN orders o ON o.id=oi.order_id
        WHERE o.status<>'CANCELLED' AND o.placed_at BETWEEN :from_dt AND :to_dt
        GROUP BY oi.product_id,oi.product_name ORDER BY quantity_sold DESC,revenue DESC,oi.product_name");
    $stmt->execute(['from_dt'=>$range['from_dt'],'to_dt'=>$range['to_dt']]);$rows=$stmt->fetchAll();
    $totalQty=0;$totalRevenue=0.0;$orders=[];foreach($rows as $r){$totalQty+=(int)$r['quantity_sold'];$totalRevenue+=(float)$r['revenue'];$orders[]=(int)$r['order_count'];}
    $catalog=$pdo->query("SELECT COUNT(*) total,COALESCE(SUM(is_active=1),0) active,COALESCE(SUM(is_featured=1 AND is_active=1),0) featured FROM products")->fetch()?:[];
    return ['summary'=>['quantity'=>$totalQty,'revenue'=>$totalRevenue,'products_sold'=>count($rows),'catalog_total'=>(int)($catalog['total']??0),'catalog_active'=>(int)($catalog['active']??0),'featured'=>(int)($catalog['featured']??0)],'rows'=>$rows];
}

function report_expenses(array $range): array
{
    $pdo=db();
    $stmt=$pdo->prepare("SELECT COUNT(*) count,COALESCE(SUM(amount),0) total,COALESCE(AVG(amount),0) average FROM expenses WHERE expense_date BETWEEN :from_date AND :to_date");
    $stmt->execute(['from_date'=>$range['from'],'to_date'=>$range['to']]);$summary=$stmt->fetch()?:[];
    $stmt=$pdo->prepare("SELECT category,COUNT(*) count,COALESCE(SUM(amount),0) total FROM expenses WHERE expense_date BETWEEN :from_date AND :to_date GROUP BY category ORDER BY total DESC");
    $stmt->execute(['from_date'=>$range['from'],'to_date'=>$range['to']]);$categories=$stmt->fetchAll();
    $stmt=$pdo->prepare("SELECT e.*,u.first_name,u.last_name FROM expenses e LEFT JOIN users u ON u.id=e.recorded_by WHERE e.expense_date BETWEEN :from_date AND :to_date ORDER BY e.expense_date DESC,e.id DESC LIMIT 1000");
    $stmt->execute(['from_date'=>$range['from'],'to_date'=>$range['to']]);
    return ['summary'=>$summary,'categories'=>$categories,'rows'=>$stmt->fetchAll()];
}

function report_bar_percent(float $value, float $max): int
{
    if ($max <= 0) return 0;
    return max(3, min(100, (int)round(($value/$max)*100)));
}
