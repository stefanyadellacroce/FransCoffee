<?php

declare(strict_types=1);

function admin_user_metrics(): array
{
    $row = db()->query("SELECT COUNT(*) total, SUM(role='ADMIN') admins, SUM(role='STAFF') staff, SUM(role='CUSTOMER') customers, SUM(status='INACTIVE') inactive FROM users")->fetch() ?: [];
    return [
        'total'=>(int)($row['total']??0),
        'admins'=>(int)($row['admins']??0),
        'staff'=>(int)($row['staff']??0),
        'customers'=>(int)($row['customers']??0),
        'inactive'=>(int)($row['inactive']??0),
    ];
}

function admin_users(array $filters=[]): array
{
    $where=['1=1']; $params=[];
    $q=trim((string)($filters['q']??''));
    $role=strtoupper(trim((string)($filters['role']??'ALL')));
    $status=strtoupper(trim((string)($filters['status']??'ALL')));
    if($q!==''){
        sql_add_search_filter($where, $params, ['u.first_name', 'u.last_name', 'u.email', 'u.phone', 'sp.employee_code'], $q);
    }
    if(in_array($role,['ADMIN','STAFF','CUSTOMER'],true)){ $where[]='u.role=:role'; $params['role']=$role; }
    if(in_array($status,['ACTIVE','INACTIVE'],true)){ $where[]='u.status=:status'; $params['status']=$status; }
    $sql="SELECT u.*,sp.employee_code,sp.job_title,cp.notifications_enabled,cp.preferred_category FROM users u LEFT JOIN staff_profiles sp ON sp.user_id=u.id LEFT JOIN customer_profiles cp ON cp.user_id=u.id WHERE ".implode(' AND ',$where)." ORDER BY FIELD(u.role,'ADMIN','STAFF','CUSTOMER'),u.created_at DESC,u.id DESC LIMIT 500";
    $stmt=db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}

function admin_user(int $id): ?array
{
    $stmt=db()->prepare("SELECT u.*,sp.employee_code,sp.job_title,cp.notifications_enabled,cp.preferred_category FROM users u LEFT JOIN staff_profiles sp ON sp.user_id=u.id LEFT JOIN customer_profiles cp ON cp.user_id=u.id WHERE u.id=:id LIMIT 1");
    $stmt->execute(['id'=>$id]); $row=$stmt->fetch(); return $row?:null;
}

function admin_active_admin_count(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchColumn();
}

function admin_next_employee_code(int $userId): string
{
    return 'FCS-STF-'.str_pad((string)$userId,4,'0',STR_PAD_LEFT);
}

function admin_validate_employee_code(string $code,int $userId=0): bool
{
    $stmt=db()->prepare('SELECT user_id FROM staff_profiles WHERE employee_code=:code AND user_id<>:user_id LIMIT 1');
    $stmt->execute(['code'=>$code,'user_id'=>$userId]); return !$stmt->fetch();
}

function admin_save_user(array $input,int $id=0): array
{
    $existing=$id>0?admin_user($id):null;
    if($id>0&&!$existing)return ['ok'=>false,'errors'=>['User not found.']];
    $first=trim((string)($input['first_name']??''));
    $last=trim((string)($input['last_name']??''));
    $email=strtolower(trim((string)($input['email']??'')));
    $phone=trim((string)($input['phone']??''));
    $role=strtoupper(trim((string)($input['role']??'CUSTOMER')));
    $status=strtoupper(trim((string)($input['status']??'ACTIVE')));
    $password=(string)($input['password']??'');
    $employeeCode=strtoupper(trim((string)($input['employee_code']??'')));
    $jobTitle=trim((string)($input['job_title']??'Staff'));
    $errors=[];
    if($first===''||mb_strlen($first)>80)$errors[]='Enter a valid first name.';
    if($last===''||mb_strlen($last)>80)$errors[]='Enter a valid last name.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)$errors[]='Enter a valid email address.';
    if($phone!==''&&mb_strlen($phone)>30)$errors[]='Phone number is too long.';
    if(!in_array($role,['ADMIN','STAFF','CUSTOMER'],true))$errors[]='Choose a valid role.';
    if(!in_array($status,['ACTIVE','INACTIVE'],true))$errors[]='Choose a valid account status.';
    if($id===0&&$password==='')$errors[]='A password is required for a new account.';
    if($password!==''&&!password_is_strong($password))$errors[]='Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    $stmt=db()->prepare('SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1');$stmt->execute(['email'=>$email,'id'=>$id]);if($stmt->fetch())$errors[]='That email address is already in use.';
    $currentId=(int)(current_user()['id']??0);
    if($existing&&$id===$currentId&&($role!=='ADMIN'||$status!=='ACTIVE'))$errors[]='You cannot deactivate or remove Admin access from the account you are currently using.';
    if($existing&&$existing['role']==='ADMIN'&&$existing['status']==='ACTIVE'&&($role!=='ADMIN'||$status!=='ACTIVE')&&admin_active_admin_count()<=1)$errors[]='At least one active administrator must remain in the system.';
    if($role==='STAFF'){
        if($jobTitle===''||mb_strlen($jobTitle)>80)$errors[]='Enter a valid staff job title.';
        if($employeeCode!==''&&!preg_match('/^[A-Z0-9_-]{3,40}$/',$employeeCode))$errors[]='Employee code may contain letters, numbers, hyphens, and underscores only.';
        if($employeeCode!==''&&!admin_validate_employee_code($employeeCode,$id))$errors[]='That employee code is already assigned to another staff account.';
    }
    if($errors)return ['ok'=>false,'errors'=>$errors];
    $pdo=db();$pdo->beginTransaction();
    try{
        if($id>0){
            $sql='UPDATE users SET first_name=:first,last_name=:last,email=:email,phone=:phone,role=:role,status=:status';
            $params=['first'=>$first,'last'=>$last,'email'=>$email,'phone'=>$phone?:null,'role'=>$role,'status'=>$status,'id'=>$id];
            if($password!==''){ $sql.=',password_hash=:password_hash'; $params['password_hash']=password_hash($password,PASSWORD_DEFAULT); }
            $sql.=' WHERE id=:id'; $pdo->prepare($sql)->execute($params);
        }else{
            $pdo->prepare('INSERT INTO users (first_name,last_name,email,phone,password_hash,role,status) VALUES (:first,:last,:email,:phone,:password_hash,:role,:status)')->execute(['first'=>$first,'last'=>$last,'email'=>$email,'phone'=>$phone?:null,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'status'=>$status]);
            $id=(int)$pdo->lastInsertId();
        }
        if($role==='STAFF'){
            $employeeCode=$employeeCode!==''?$employeeCode:admin_next_employee_code($id);
            if(!admin_validate_employee_code($employeeCode,$id))throw new RuntimeException('Unable to generate a unique employee code.');
            $pdo->prepare("INSERT INTO staff_profiles (user_id,employee_code,job_title) VALUES (:uid,:code,:title) ON DUPLICATE KEY UPDATE employee_code=VALUES(employee_code),job_title=VALUES(job_title)")->execute(['uid'=>$id,'code'=>$employeeCode,'title'=>$jobTitle]);
            $pdo->prepare('DELETE FROM customer_profiles WHERE user_id=:uid')->execute(['uid'=>$id]);
        }elseif($role==='CUSTOMER'){
            $pdo->prepare('INSERT IGNORE INTO customer_profiles (user_id) VALUES (:uid)')->execute(['uid'=>$id]);
            $pdo->prepare('DELETE FROM staff_profiles WHERE user_id=:uid')->execute(['uid'=>$id]);
        }else{
            $pdo->prepare('DELETE FROM staff_profiles WHERE user_id=:uid')->execute(['uid'=>$id]);
            $pdo->prepare('DELETE FROM customer_profiles WHERE user_id=:uid')->execute(['uid'=>$id]);
        }
        $pdo->commit();
        if($id===$currentId){
            $_SESSION['user']['first_name']=$first;$_SESSION['user']['last_name']=$last;$_SESSION['user']['email']=$email;$_SESSION['user']['role']=$role;
        }
        log_activity($currentId,$existing?'UPDATE_USER':'CREATE_USER','users',$id,['role'=>$role,'status'=>$status,'email'=>$email]);
        return ['ok'=>true,'id'=>$id,'message'=>$existing?'User account updated.':'User account created.'];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'errors'=>[$e->getMessage()]];}
}

function admin_toggle_user_status(int $id): array
{
    $user=admin_user($id);if(!$user)return ['ok'=>false,'message'=>'User not found.'];
    $currentId=(int)(current_user()['id']??0);if($id===$currentId)return ['ok'=>false,'message'=>'You cannot deactivate the account you are currently using.'];
    if($user['role']==='ADMIN'&&$user['status']==='ACTIVE'&&admin_active_admin_count()<=1)return ['ok'=>false,'message'=>'At least one active administrator must remain in the system.'];
    $new=$user['status']==='ACTIVE'?'INACTIVE':'ACTIVE';db()->prepare('UPDATE users SET status=:status WHERE id=:id')->execute(['status'=>$new,'id'=>$id]);
    log_activity($currentId,'TOGGLE_USER_STATUS','users',$id,['status'=>$new]);return ['ok'=>true,'message'=>'User account '.strtolower($new).'.'];
}

function admin_activity_logs(array $filters=[]): array
{
    $where=['1=1'];$params=[];$q=trim((string)($filters['q']??''));$action=trim((string)($filters['action']??''));$entity=trim((string)($filters['entity']??''));$from=trim((string)($filters['from']??''));$to=trim((string)($filters['to']??''));
    if($q!=='') sql_add_search_filter($where, $params, ['u.first_name', 'u.last_name', 'u.email', 'a.action', 'a.entity_type', 'a.ip_address'], $q);
    if($action!==''){ $where[]='a.action=:action';$params['action']=$action; }
    if($entity!==''){ $where[]='a.entity_type=:entity';$params['entity']=$entity; }
    if($from!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where[]='a.created_at>=:from';$params['from']=$from.' 00:00:00'; }
    if($to!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $where[]='a.created_at<=:to';$params['to']=$to.' 23:59:59'; }
    $stmt=db()->prepare("SELECT a.*,u.first_name,u.last_name,u.email,u.role FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE ".implode(' AND ',$where)." ORDER BY a.created_at DESC,a.id DESC LIMIT 500");$stmt->execute($params);return $stmt->fetchAll();
}

function admin_activity_filter_options(): array
{
    return [
        'actions'=>db()->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN),
        'entities'=>db()->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL AND entity_type<>'' ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function admin_dashboard_metrics(): array
{
    $users=admin_user_metrics();
    $orders=db()->query("SELECT COALESCE(SUM(DATE(created_at)=CURDATE()),0) orders_today,COALESCE(SUM(status='PENDING'),0) pending,COALESCE(SUM(status='COMPLETED'),0) completed,COALESCE(SUM(created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND status<>'CANCELLED'),0) last30 FROM orders")->fetch()?:[];
    $sales=db()->query("SELECT COALESCE(SUM(CASE WHEN DATE(p.verified_at)=CURDATE() THEN p.amount ELSE 0 END),0) today,COALESCE(SUM(CASE WHEN p.verified_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) THEN p.amount ELSE 0 END),0) last30 FROM payments p WHERE p.status='PAID'")->fetch()?:[];
    $newCustomers=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='CUSTOMER' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
    return ['users'=>$users,'orders_today'=>(int)($orders['orders_today']??0),'pending'=>(int)($orders['pending']??0),'completed'=>(int)($orders['completed']??0),'sales_today'=>(float)($sales['today']??0),'sales_30'=>(float)($sales['last30']??0),'new_customers'=>$newCustomers];
}

function admin_recent_orders(int $limit=8): array
{
    $limit=max(1,min(20,$limit));
    return db()->query("SELECT o.*,pm.name payment_method,p.status payment_status FROM orders o LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id ORDER BY o.created_at DESC,o.id DESC LIMIT ".$limit)->fetchAll();
}

function admin_top_products(int $limit=5): array
{
    $limit=max(1,min(10,$limit));
    return db()->query("SELECT oi.product_name,SUM(oi.quantity) qty,COALESCE(SUM(oi.line_total),0) revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status<>'CANCELLED' GROUP BY oi.product_name ORDER BY qty DESC,revenue DESC LIMIT ".$limit)->fetchAll();
}

function admin_sales_days(int $days=7): array
{
    $days=max(3,min(31,$days));$start=(new DateTimeImmutable('today'))->modify('-'.($days-1).' days');
    $stmt=db()->prepare("SELECT DATE(p.verified_at) d,COALESCE(SUM(p.amount),0) total FROM payments p WHERE p.status='PAID' AND p.verified_at>=:start GROUP BY DATE(p.verified_at)");$stmt->execute(['start'=>$start->format('Y-m-d').' 00:00:00']);$map=[];foreach($stmt->fetchAll() as $r)$map[$r['d']]=(float)$r['total'];$rows=[];for($i=0;$i<$days;$i++){ $d=$start->modify('+'.$i.' days');$key=$d->format('Y-m-d');$rows[]=['date'=>$key,'label'=>$d->format('D'),'total'=>$map[$key]??0.0]; }return $rows;
}

function admin_setting_keys(): array
{
    return ['shop_name','tagline','contact_email','contact_phone','store_address','business_days','opening_time','closing_time','business_hours','logo_path','pickup_enabled','delivery_enabled','minimum_order','delivery_fee'];
}

function admin_settings_values(): array
{
    $rows=db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll();$values=[];foreach($rows as $r)$values[(string)$r['setting_key']]=(string)($r['setting_value']??'');return $values;
}

function admin_upsert_setting(PDO $pdo,string $key,string $value): void
{
    $stmt=$pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (:k,:v) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$stmt->execute(['k'=>$key,'v'=>$value]);
}

function admin_image_upload(array $file,string $subdir,string $prefix,int $maxBytes=5242880): array
{
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return ['ok'=>true,'path'=>null];
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)return ['ok'=>false,'error'=>'The image upload failed.'];
    if((int)($file['size']??0)<=0||(int)$file['size']>$maxBytes)return ['ok'=>false,'error'=>'Image must be 5 MB or smaller.'];
    $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);if(!$info)return ['ok'=>false,'error'=>'Upload a valid image file.'];
    $mime=(string)($info['mime']??'');$ext=match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>''};if($ext==='')return ['ok'=>false,'error'=>'Only JPG, PNG, and WEBP images are allowed.'];
    $dir=dirname(__DIR__).'/uploads/'.trim($subdir,'/');if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))return ['ok'=>false,'error'=>'Unable to prepare the upload directory.'];
    $name=$prefix.'-'.bin2hex(random_bytes(10)).'.'.$ext;$target=$dir.'/'.$name;if(!move_uploaded_file($tmp,$target))return ['ok'=>false,'error'=>'Unable to save the uploaded image.'];
    return ['ok'=>true,'path'=>'uploads/'.trim($subdir,'/').'/'.$name];
}

function admin_remove_managed_upload(?string $path): void
{
    $path=(string)$path;if($path===''||!str_starts_with($path,'uploads/'))return;$full=dirname(__DIR__).'/'.$path;$base=realpath(dirname(__DIR__).'/uploads');$real=realpath($full);if($base&&$real&&str_starts_with($real,$base)&&is_file($real))@unlink($real);
}

function admin_save_settings(array $input,array $files=[]): array
{
    $shopName=trim((string)($input['shop_name']??''));
    $tagline=trim((string)($input['tagline']??''));
    $email=strtolower(trim((string)($input['contact_email']??'')));
    $phone=trim((string)($input['contact_phone']??''));
    $address=trim((string)($input['store_address']??''));
    $days=trim((string)($input['business_days']??'Monday–Sunday'));
    $open=trim((string)($input['opening_time']??'07:00'));
    $close=trim((string)($input['closing_time']??'21:00'));
    $min=max(0,round((float)($input['minimum_order']??0),2));
    $fee=max(0,round((float)($input['delivery_fee']??0),2));
    $pickup=!empty($input['pickup_enabled']);
    $delivery=!empty($input['delivery_enabled']);
    $errors=[];
    if($shopName===''||mb_strlen($shopName)>120)$errors[]='Enter a valid shop name.';
    if($tagline===''||mb_strlen($tagline)>180)$errors[]='Enter a valid tagline.';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid contact email.';
    if($phone===''||mb_strlen($phone)>40)$errors[]='Enter a valid contact number.';
    if($address===''||mb_strlen($address)>255)$errors[]='Enter the store address.';
    if($days===''||mb_strlen($days)>100)$errors[]='Enter the business days.';
    if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$open)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$close))$errors[]='Choose valid opening and closing times.';
    if(!$pickup&&!$delivery)$errors[]='Enable at least Store Pickup or Delivery.';

    $settings=admin_settings_values();
    $logoPath=(string)($settings['logo_path']??'');
    $payments=[];
    foreach(['GCASH'=>'gcash','MAYA'=>'maya'] as $code=>$prefix){
        $name=trim((string)($input[$prefix.'_receiver_name']??''));
        $number=trim((string)($input[$prefix.'_receiver_number']??''));
        $active=!empty($input[$prefix.'_active']);
        if($active&&($name===''||$number===''))$errors[]=$code.' receiver name and number are required when the method is active.';
        $method=checkout_payment_method_any($code)?:[];
        $payments[$code]=['name'=>$name,'number'=>$number,'active'=>$active?1:0,'qr'=>(string)($method['qr_path']??''),'prefix'=>$prefix];
    }
    if($errors)return ['ok'=>false,'errors'=>$errors];

    $newUploads=[];$oldToDelete=[];
    if(!empty($input['remove_logo'])){if(str_starts_with($logoPath,'uploads/'))$oldToDelete[]=$logoPath;$logoPath='';}
    if(isset($files['shop_logo'])){
        $u=admin_image_upload($files['shop_logo'],'settings','logo');
        if(!$u['ok'])return ['ok'=>false,'errors'=>[$u['error']]];
        if($u['path']){if(str_starts_with($logoPath,'uploads/'))$oldToDelete[]=$logoPath;$logoPath=$u['path'];$newUploads[]=$u['path'];}
    }
    foreach($payments as $code=>&$p){
        $prefix=$p['prefix'];
        if(!empty($input[$prefix.'_remove_qr'])){if(str_starts_with($p['qr'],'uploads/'))$oldToDelete[]=$p['qr'];$p['qr']='';}
        if(isset($files[$prefix.'_qr'])){
            $u=admin_image_upload($files[$prefix.'_qr'],'payments',strtolower($code).'-qr');
            if(!$u['ok']){foreach($newUploads as $path)admin_remove_managed_upload($path);return ['ok'=>false,'errors'=>[$u['error']]];}
            if($u['path']){if(str_starts_with($p['qr'],'uploads/'))$oldToDelete[]=$p['qr'];$p['qr']=$u['path'];$newUploads[]=$u['path'];}
        }
    }
    unset($p);

    $businessHours=$days.' · '.admin_time_label($open).'–'.admin_time_label($close);
    $pdo=db();$pdo->beginTransaction();
    try{
        foreach(['shop_name'=>$shopName,'tagline'=>$tagline,'contact_email'=>$email,'contact_phone'=>$phone,'store_address'=>$address,'business_days'=>$days,'opening_time'=>$open,'closing_time'=>$close,'business_hours'=>$businessHours,'logo_path'=>$logoPath,'pickup_enabled'=>$pickup?'1':'0','delivery_enabled'=>$delivery?'1':'0','minimum_order'=>number_format($min,2,'.',''),'delivery_fee'=>number_format($fee,2,'.','')] as $k=>$v)admin_upsert_setting($pdo,$k,$v);
        $stmt=$pdo->prepare('UPDATE payment_methods SET receiver_name=:name,receiver_number=:number,qr_path=:qr,is_active=:active WHERE code=:code');
        foreach($payments as $code=>$p)$stmt->execute(['name'=>$p['name']?:null,'number'=>$p['number']?:null,'qr'=>$p['qr']?:null,'active'=>$p['active'],'code'=>$code]);
        $pdo->commit();
        foreach(array_unique($oldToDelete) as $path){if(!in_array($path,$newUploads,true))admin_remove_managed_upload($path);}
        log_activity((int)(current_user()['id']??0),'UPDATE_SETTINGS','settings',null,['pickup'=>$pickup,'delivery'=>$delivery]);
        return ['ok'=>true,'message'=>'System settings updated.'];
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        foreach($newUploads as $path)admin_remove_managed_upload($path);
        return ['ok'=>false,'errors'=>[$e->getMessage()]];
    }
}

function admin_time_label(string $time): string
{
    $dt=DateTimeImmutable::createFromFormat('H:i',$time);return $dt?$dt->format('g:i A'):$time;
}

function checkout_payment_method_any(string $code): ?array
{
    $stmt=db()->prepare('SELECT * FROM payment_methods WHERE code=:code LIMIT 1');$stmt->execute(['code'=>strtoupper($code)]);$row=$stmt->fetch();return $row?:null;
}
