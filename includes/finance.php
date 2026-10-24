<?php

declare(strict_types=1);

function admin_payment_metrics(): array
{
    $row = db()->query("SELECT COUNT(*) total, SUM(status='PENDING') pending, SUM(status='PAID') paid, SUM(status='FAILED') failed, SUM(status='REFUNDED') refunded, COALESCE(SUM(CASE WHEN status='PAID' THEN amount ELSE 0 END),0) paid_amount FROM payments")->fetch();
    return [
        'total' => (int)($row['total'] ?? 0), 'pending' => (int)($row['pending'] ?? 0),
        'paid' => (int)($row['paid'] ?? 0), 'failed' => (int)($row['failed'] ?? 0),
        'refunded' => (int)($row['refunded'] ?? 0), 'paid_amount' => (float)($row['paid_amount'] ?? 0),
    ];
}

function admin_payments(array $filters = []): array
{
    $where = ['1=1']; $params = [];
    $q = trim((string)($filters['q'] ?? ''));
    $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
    $method = strtoupper(trim((string)($filters['method'] ?? 'ALL')));
    if ($q !== '') sql_add_search_filter($where, $params, ['p.payment_number', 'o.order_number', 'o.contact_name', 'p.reference_number'], $q);
    if ($status !== 'ALL' && in_array($status, ['UNPAID','PENDING','PAID','FAILED','REFUNDED'], true)) { $where[] = 'p.status=:status'; $params['status']=$status; }
    if ($method !== 'ALL') { $where[] = 'pm.code=:method'; $params['method']=$method; }
    $sql = "SELECT p.*,o.order_number,o.contact_name,o.order_source,o.fulfillment_type,o.status AS order_status,pm.code AS method_code,pm.name AS method_name,u.first_name AS verifier_first,u.last_name AS verifier_last FROM payments p JOIN orders o ON o.id=p.order_id JOIN payment_methods pm ON pm.id=p.payment_method_id LEFT JOIN users u ON u.id=p.verified_by WHERE " . implode(' AND ', $where) . ' ORDER BY p.created_at DESC,p.id DESC LIMIT 500';
    $stmt=db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}

function admin_refund_payment(int $paymentId, string $note = ''): array
{
    $pdo=db(); $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare("SELECT p.*,o.order_number FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.id=:id FOR UPDATE");
        $stmt->execute(['id'=>$paymentId]); $payment=$stmt->fetch();
        if (!$payment) throw new RuntimeException('Payment not found.');
        if ($payment['status'] !== 'PAID') throw new RuntimeException('Only paid payments can be marked refunded.');
        $uid=(int)(current_user()['id'] ?? 0);
        $pdo->prepare("UPDATE payments SET status='REFUNDED',verified_by=:uid,verified_at=NOW(),verification_note=:note WHERE id=:id")->execute(['uid'=>$uid,'note'=>$note !== '' ? mb_substr($note,0,255) : 'Refund recorded by administrator','id'=>$paymentId]);
        $pdo->commit();
        log_activity($uid,'REFUND_PAYMENT','payments',$paymentId,['payment_number'=>$payment['payment_number'],'order_number'=>$payment['order_number']]);
        return ['ok'=>true,'message'=>'Payment marked as refunded.'];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return ['ok'=>false,'message'=>$e->getMessage()]; }
}

function expense_number(int $id, ?string $date = null): string
{
    $stamp = date('Ymd', $date ? strtotime($date) : time());
    return 'FCS-EXP-' . $stamp . '-' . str_pad((string)$id,4,'0',STR_PAD_LEFT);
}

function admin_expense_metrics(): array
{
    $row=db()->query("SELECT COUNT(*) total,COALESCE(SUM(amount),0) total_amount,COALESCE(SUM(CASE WHEN expense_date=CURDATE() THEN amount ELSE 0 END),0) today,COALESCE(SUM(CASE WHEN YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE()) THEN amount ELSE 0 END),0) month_amount FROM expenses")->fetch();
    return ['total'=>(int)($row['total']??0),'total_amount'=>(float)($row['total_amount']??0),'today'=>(float)($row['today']??0),'month_amount'=>(float)($row['month_amount']??0)];
}

function admin_expenses(array $filters=[]): array
{
    $where=['1=1']; $params=[]; $q=trim((string)($filters['q']??'')); $category=trim((string)($filters['category']??''));
    $from=trim((string)($filters['from']??'')); $to=trim((string)($filters['to']??''));
    if ($q!=='') sql_add_search_filter($where, $params, ['e.expense_number', 'e.description'], $q);
    if ($category!=='') { $where[]='e.category=:category'; $params['category']=$category; }
    if ($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) { $where[]='e.expense_date>=:from'; $params['from']=$from; }
    if ($to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) { $where[]='e.expense_date<=:to'; $params['to']=$to; }
    $stmt=db()->prepare("SELECT e.*,u.first_name,u.last_name FROM expenses e LEFT JOIN users u ON u.id=e.recorded_by WHERE ".implode(' AND ',$where)." ORDER BY e.expense_date DESC,e.id DESC LIMIT 500"); $stmt->execute($params); return $stmt->fetchAll();
}

function admin_expense(int $id): ?array
{
    $stmt=db()->prepare('SELECT * FROM expenses WHERE id=:id LIMIT 1'); $stmt->execute(['id'=>$id]); $row=$stmt->fetch(); return $row?:null;
}

function admin_save_expense(array $input, int $id=0): array
{
    $categories=['Inventory','Utilities','Rent','Salary','Repairs','Equipment','Miscellaneous'];
    $category=trim((string)($input['category']??'')); $amount=round((float)($input['amount']??0),2); $date=trim((string)($input['expense_date']??'')); $desc=trim((string)($input['description']??''));
    $errors=[]; if(!in_array($category,$categories,true))$errors[]='Choose a valid expense category.'; if($amount<=0)$errors[]='Amount must be greater than zero.'; if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))$errors[]='Choose a valid expense date.'; if($desc===''||mb_strlen($desc)>500)$errors[]='Enter a description up to 500 characters.'; if($errors)return ['ok'=>false,'errors'=>$errors];
    $uid=(int)(current_user()['id']??0); $pdo=db();
    if($id>0){ $stmt=$pdo->prepare('UPDATE expenses SET category=:category,amount=:amount,expense_date=:date,description=:description WHERE id=:id'); $stmt->execute(['category'=>$category,'amount'=>$amount,'date'=>$date,'description'=>$desc,'id'=>$id]); log_activity($uid,'UPDATE_EXPENSE','expenses',$id,['amount'=>$amount]); return ['ok'=>true,'id'=>$id,'message'=>'Expense updated.']; }
    $temp='TEMP-'.bin2hex(random_bytes(8)); $stmt=$pdo->prepare('INSERT INTO expenses (expense_number,category,amount,expense_date,description,recorded_by) VALUES (:number,:category,:amount,:date,:description,:uid)'); $stmt->execute(['number'=>$temp,'category'=>$category,'amount'=>$amount,'date'=>$date,'description'=>$desc,'uid'=>$uid]); $id=(int)$pdo->lastInsertId(); $number=expense_number($id,$date); $pdo->prepare('UPDATE expenses SET expense_number=:number WHERE id=:id')->execute(['number'=>$number,'id'=>$id]); log_activity($uid,'CREATE_EXPENSE','expenses',$id,['expense_number'=>$number,'amount'=>$amount]); return ['ok'=>true,'id'=>$id,'message'=>'Expense recorded.'];
}

function admin_promotions(array $filters=[]): array
{
    $where=['1=1'];$params=[];$q=trim((string)($filters['q']??''));$status=strtoupper(trim((string)($filters['status']??'ALL')));
    if($q!=='') sql_add_search_filter($where, $params, ['p.code', 'p.name'], $q);
    if($status!=='ALL'&&in_array($status,['ACTIVE','INACTIVE'],true)){ $where[]='p.status=:status';$params['status']=$status; }
    $stmt=db()->prepare("SELECT p.*,COUNT(pu.id) usage_count,COALESCE(SUM(pu.discount_amount),0) discount_given FROM promotions p LEFT JOIN promotion_usages pu ON pu.promotion_id=p.id WHERE ".implode(' AND ',$where)." GROUP BY p.id ORDER BY p.created_at DESC,p.id DESC");$stmt->execute($params);return $stmt->fetchAll();
}

function admin_promotion(int $id): ?array
{
    $stmt=db()->prepare('SELECT * FROM promotions WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$row=$stmt->fetch();return $row?:null;
}

function admin_save_promotion(array $input,int $id=0): array
{
    $code=strtoupper(preg_replace('/[^A-Z0-9_-]/','',strtoupper(trim((string)($input['code']??'')))));$name=trim((string)($input['name']??''));$type=strtoupper(trim((string)($input['discount_type']??'')));$value=round((float)($input['discount_value']??0),2);$min=round(max(0,(float)($input['minimum_spend']??0)),2);$start=trim((string)($input['start_date']??''));$end=trim((string)($input['end_date']??''));$limit=max(0,(int)($input['usage_limit']??0));$per=max(0,(int)($input['per_customer_limit']??1));$status=strtoupper(trim((string)($input['status']??'ACTIVE')));
    $errors=[];if($code===''||mb_strlen($code)>40)$errors[]='Enter a promo code up to 40 characters.';if($name===''||mb_strlen($name)>140)$errors[]='Enter a promotion name.';if(!in_array($type,['PERCENT','FIXED'],true))$errors[]='Choose a discount type.';if($value<=0)$errors[]='Discount value must be greater than zero.';if($type==='PERCENT'&&$value>100)$errors[]='Percentage discount cannot exceed 100%.';if(!in_array($status,['ACTIVE','INACTIVE'],true))$status='ACTIVE';if($start!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start))$errors[]='Start date is invalid.';if($end!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))$errors[]='End date is invalid.';if($start!==''&&$end!==''&&$end<$start)$errors[]='End date must not be before the start date.';
    $stmt=db()->prepare('SELECT id FROM promotions WHERE code=:code AND id<>:id LIMIT 1');$stmt->execute(['code'=>$code,'id'=>$id]);if($stmt->fetch())$errors[]='That promo code already exists.';if($errors)return ['ok'=>false,'errors'=>$errors];
    $uid=(int)(current_user()['id']??0);$params=['code'=>$code,'name'=>$name,'type'=>$type,'value'=>$value,'min'=>$min,'start'=>$start?:null,'end'=>$end?:null,'limit'=>$limit?:null,'per'=>$per?:null,'status'=>$status];
    if($id>0){$params['id']=$id;db()->prepare('UPDATE promotions SET code=:code,name=:name,discount_type=:type,discount_value=:value,minimum_spend=:min,start_date=:start,end_date=:end,usage_limit=:limit,per_customer_limit=:per,status=:status WHERE id=:id')->execute($params);log_activity($uid,'UPDATE_PROMOTION','promotions',$id,['code'=>$code]);return ['ok'=>true,'id'=>$id,'message'=>'Promotion updated.'];}
    db()->prepare('INSERT INTO promotions (code,name,discount_type,discount_value,minimum_spend,start_date,end_date,usage_limit,per_customer_limit,status,created_by) VALUES (:code,:name,:type,:value,:min,:start,:end,:limit,:per,:status,:uid)')->execute($params+['uid'=>$uid]);$id=(int)db()->lastInsertId();log_activity($uid,'CREATE_PROMOTION','promotions',$id,['code'=>$code]);return ['ok'=>true,'id'=>$id,'message'=>'Promotion created.'];
}

function admin_toggle_promotion(int $id): array
{
    $promo=admin_promotion($id);if(!$promo)return ['ok'=>false,'message'=>'Promotion not found.'];$new=$promo['status']==='ACTIVE'?'INACTIVE':'ACTIVE';db()->prepare('UPDATE promotions SET status=:status WHERE id=:id')->execute(['status'=>$new,'id'=>$id]);log_activity((int)(current_user()['id']??0),'TOGGLE_PROMOTION','promotions',$id,['status'=>$new]);return ['ok'=>true,'message'=>'Promotion '.strtolower($new).'.'];
}

function promotion_validate(string $code,float $subtotal,int $userId=0,bool $lock=false,?PDO $pdo=null): array
{
    $code=strtoupper(trim($code)); if($code==='')return ['ok'=>true,'promotion'=>null,'discount'=>0.0,'message'=>''];
    $pdo=$pdo?:db(); $sql='SELECT * FROM promotions WHERE code=:code LIMIT 1'.($lock?' FOR UPDATE':'');$stmt=$pdo->prepare($sql);$stmt->execute(['code'=>$code]);$p=$stmt->fetch();
    if(!$p||$p['status']!=='ACTIVE')return ['ok'=>false,'message'=>'Promo code is not available.'];$today=date('Y-m-d');if(!empty($p['start_date'])&&$today<$p['start_date'])return ['ok'=>false,'message'=>'This promo has not started yet.'];if(!empty($p['end_date'])&&$today>$p['end_date'])return ['ok'=>false,'message'=>'This promo has expired.'];if($subtotal<(float)$p['minimum_spend'])return ['ok'=>false,'message'=>'Spend at least '.money((float)$p['minimum_spend']).' to use this promo.'];
    if(!empty($p['usage_limit'])){$s=$pdo->prepare('SELECT COUNT(*) FROM promotion_usages WHERE promotion_id=:id');$s->execute(['id'=>$p['id']]);if((int)$s->fetchColumn()>=(int)$p['usage_limit'])return ['ok'=>false,'message'=>'This promo has reached its usage limit.'];}
    if($userId>0&&!empty($p['per_customer_limit'])){$s=$pdo->prepare('SELECT COUNT(*) FROM promotion_usages WHERE promotion_id=:id AND user_id=:uid');$s->execute(['id'=>$p['id'],'uid'=>$userId]);if((int)$s->fetchColumn()>=(int)$p['per_customer_limit'])return ['ok'=>false,'message'=>'You have already used this promo the maximum number of times.'];}
    $discount=$p['discount_type']==='PERCENT'?$subtotal*((float)$p['discount_value']/100):(float)$p['discount_value'];$discount=round(min($subtotal,max(0,$discount)),2);return ['ok'=>true,'promotion'=>$p,'discount'=>$discount,'message'=>'Promo applied: '.money($discount).' off.'];
}
