<?php

declare(strict_types=1);

function catalog_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') return 'item';
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') $value = $converted;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'item';
}

function catalog_unique_slug(string $table, string $name, int $excludeId = 0): string
{
    if (!in_array($table, ['categories', 'products'], true)) {
        throw new InvalidArgumentException('Unsupported catalog table.');
    }
    $base = substr(catalog_slugify($name), 0, $table === 'products' ? 150 : 110);
    $slug = $base;
    $suffix = 2;
    $stmt = db()->prepare("SELECT id FROM {$table} WHERE slug = :slug AND id <> :exclude_id LIMIT 1");
    while (true) {
        $stmt->execute(['slug' => $slug, 'exclude_id' => $excludeId]);
        if (!$stmt->fetch()) return $slug;
        $tail = '-' . $suffix++;
        $slug = substr($base, 0, max(1, ($table === 'products' ? 150 : 110) - strlen($tail))) . $tail;
    }
}

function admin_categories(): array
{
    return db()->query(
        'SELECT c.*, COUNT(p.id) AS product_count, SUM(CASE WHEN p.is_active=1 THEN 1 ELSE 0 END) AS active_product_count '
        . 'FROM categories c LEFT JOIN products p ON p.category_id=c.id '
        . 'GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC'
    )->fetchAll();
}

function admin_category_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_save_category(array $data, int $id = 0): array
{
    $name = trim((string)($data['name'] ?? ''));
    $sortOrder = filter_var($data['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $errors = [];
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Category name is required and must be 100 characters or fewer.';
    if ($sortOrder === false || $sortOrder < 0 || $sortOrder > 9999) $errors[] = 'Sort order must be between 0 and 9999.';
    if ($id > 0 && !admin_category_by_id($id)) $errors[] = 'Category not found.';

    $stmt = db()->prepare('SELECT id FROM categories WHERE LOWER(name)=LOWER(:name) AND id<>:id LIMIT 1');
    $stmt->execute(['name' => $name, 'id' => $id]);
    if ($name !== '' && $stmt->fetch()) $errors[] = 'A category with that name already exists.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $slug = catalog_unique_slug('categories', $name, $id);
    if ($id > 0) {
        $stmt = db()->prepare('UPDATE categories SET name=:name,slug=:slug,sort_order=:sort_order,is_active=:is_active,updated_at=NOW() WHERE id=:id');
        $stmt->execute(['name'=>$name,'slug'=>$slug,'sort_order'=>$sortOrder,'is_active'=>$isActive,'id'=>$id]);
        log_activity((int)current_user()['id'], 'UPDATE_CATEGORY', 'categories', $id, ['active'=>$isActive]);
        return ['ok'=>true,'id'=>$id,'message'=>'Category updated.'];
    }

    $stmt = db()->prepare('INSERT INTO categories (name,slug,sort_order,is_active) VALUES (:name,:slug,:sort_order,:is_active)');
    $stmt->execute(['name'=>$name,'slug'=>$slug,'sort_order'=>$sortOrder,'is_active'=>$isActive]);
    $newId=(int)db()->lastInsertId();
    log_activity((int)current_user()['id'], 'CREATE_CATEGORY', 'categories', $newId);
    return ['ok'=>true,'id'=>$newId,'message'=>'Category created.'];
}

function admin_toggle_category(int $id): array
{
    $category = admin_category_by_id($id);
    if (!$category) return ['ok'=>false,'message'=>'Category not found.'];
    $next = (int)$category['is_active'] === 1 ? 0 : 1;
    db()->prepare('UPDATE categories SET is_active=:active,updated_at=NOW() WHERE id=:id')->execute(['active'=>$next,'id'=>$id]);
    log_activity((int)current_user()['id'], $next ? 'ENABLE_CATEGORY' : 'DISABLE_CATEGORY', 'categories', $id);
    return ['ok'=>true,'message'=>$next ? 'Category enabled.' : 'Category disabled. Products in it are hidden from the storefront while disabled.'];
}

function admin_products(array $filters=[]): array
{
    $where=['1=1']; $params=[];
    $q=trim((string)($filters['q']??''));
    if ($q!=='') sql_add_search_filter($where, $params, ['p.name', 'p.slug', 'p.description'], $q);
    $category=(int)($filters['category_id']??0);
    if ($category>0) { $where[]='p.category_id=:category_id'; $params['category_id']=$category; }
    $status=strtoupper((string)($filters['status']??'ALL'));
    if ($status==='ACTIVE') $where[]='p.is_active=1';
    if ($status==='INACTIVE') $where[]='p.is_active=0';
    if (!empty($filters['featured'])) $where[]='p.is_featured=1';
    $sql='SELECT p.*,c.name AS category_name,c.is_active AS category_active,'
        .'(SELECT COUNT(*) FROM product_sizes s WHERE s.product_id=p.id AND s.is_active=1) AS active_sizes,'
        .'(SELECT COUNT(*) FROM product_addons a WHERE a.product_id=p.id AND a.is_active=1) AS active_addons '
        .'FROM products p JOIN categories c ON c.id=p.category_id WHERE '.implode(' AND ',$where)
        .' ORDER BY p.updated_at DESC,p.name ASC';
    $stmt=db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}

function admin_product_counts(): array
{
    $row=db()->query('SELECT COUNT(*) total,SUM(is_active=1) active_count,SUM(is_active=0) inactive_count,SUM(is_featured=1 AND is_active=1) featured_count FROM products')->fetch();
    return ['total'=>(int)($row['total']??0),'active'=>(int)($row['active_count']??0),'inactive'=>(int)($row['inactive_count']??0),'featured'=>(int)($row['featured_count']??0)];
}

function admin_product_by_id(int $id): ?array
{
    $stmt=db()->prepare('SELECT p.*,c.name AS category_name,c.is_active AS category_active FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=:id LIMIT 1');
    $stmt->execute(['id'=>$id]); $row=$stmt->fetch(); return $row?:null;
}

function catalog_upload_product_image(array $file): array
{
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if ($error===UPLOAD_ERR_NO_FILE) return ['ok'=>true,'path'=>null];
    if ($error!==UPLOAD_ERR_OK) return ['ok'=>false,'message'=>'The product image upload failed. Please try another file.'];
    $size=(int)($file['size']??0);
    if ($size<=0 || $size>5*1024*1024) return ['ok'=>false,'message'=>'Product images must be 5 MB or smaller.'];
    $tmp=(string)($file['tmp_name']??'');
    if ($tmp==='' || !is_uploaded_file($tmp)) return ['ok'=>false,'message'=>'The uploaded image could not be verified.'];
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file($tmp);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return ['ok'=>false,'message'=>'Use a JPG, PNG, or WEBP product image.'];
    $dir=dirname(__DIR__).'/uploads/products';
    if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) return ['ok'=>false,'message'=>'The product upload folder could not be created.'];
    $name='product_'.bin2hex(random_bytes(16)).'.'.$allowed[$mime];
    if (!move_uploaded_file($tmp,$dir.'/'.$name)) return ['ok'=>false,'message'=>'The product image could not be saved.'];
    return ['ok'=>true,'path'=>'uploads/products/'.$name];
}

function catalog_delete_uploaded_product_image(?string $path): void
{
    $path=trim((string)$path);
    if ($path==='' || !str_starts_with($path,'uploads/products/')) return;
    $file=dirname(__DIR__).'/'.$path;
    $realDir=realpath(dirname(__DIR__).'/uploads/products');
    $realFile=realpath($file);
    if ($realDir && $realFile && str_starts_with($realFile,$realDir.DIRECTORY_SEPARATOR) && is_file($realFile)) @unlink($realFile);
}

function admin_save_product(array $data, ?array $file, int $id=0): array
{
    $existing=$id>0?admin_product_by_id($id):null;
    if ($id>0 && !$existing) return ['ok'=>false,'errors'=>['Product not found.']];
    $name=trim((string)($data['name']??''));
    $categoryId=(int)($data['category_id']??0);
    $description=trim((string)($data['description']??''));
    $baseRaw=(string)($data['base_price']??'');
    $prepRaw=$data['prep_minutes']??10;
    $base=filter_var($baseRaw,FILTER_VALIDATE_FLOAT);
    $prep=filter_var($prepRaw,FILTER_VALIDATE_INT);
    $isActive=!empty($data['is_active'])?1:0;
    $isFeatured=!empty($data['is_featured'])?1:0;
    $errors=[];
    if ($name==='' || mb_strlen($name)>140) $errors[]='Product name is required and must be 140 characters or fewer.';
    if (!$categoryId || !admin_category_by_id($categoryId)) $errors[]='Choose a valid category.';
    if (mb_strlen($description)>3000) $errors[]='Description must be 3000 characters or fewer.';
    if ($base===false || $base<0 || $base>999999.99) $errors[]='Enter a valid base price.';
    if ($prep===false || $prep<0 || $prep>240) $errors[]='Preparation time must be between 0 and 240 minutes.';
    if ($errors) return ['ok'=>false,'errors'=>$errors];

    $upload=['ok'=>true,'path'=>null];
    if ($file && (int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
        $upload=catalog_upload_product_image($file);
        if (!$upload['ok']) return ['ok'=>false,'errors'=>[$upload['message']]];
    }
    $newPath=$upload['path']??null;
    $imagePath=$newPath ?: ($existing['image_path']??null);
    $slug=catalog_unique_slug('products',$name,$id);
    $pdo=db();
    try {
        $pdo->beginTransaction();
        if ($id>0) {
            $stmt=$pdo->prepare('UPDATE products SET category_id=:category_id,name=:name,slug=:slug,description=:description,base_price=:base_price,image_path=:image_path,prep_minutes=:prep_minutes,is_featured=:is_featured,is_active=:is_active,updated_at=NOW() WHERE id=:id');
            $stmt->execute(['category_id'=>$categoryId,'name'=>$name,'slug'=>$slug,'description'=>$description!==''?$description:null,'base_price'=>$base,'image_path'=>$imagePath,'prep_minutes'=>$prep,'is_featured'=>$isFeatured,'is_active'=>$isActive,'id'=>$id]);
            $productId=$id;
        } else {
            $stmt=$pdo->prepare('INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active) VALUES (:category_id,:name,:slug,:description,:base_price,:image_path,:prep_minutes,:is_featured,:is_active)');
            $stmt->execute(['category_id'=>$categoryId,'name'=>$name,'slug'=>$slug,'description'=>$description!==''?$description:null,'base_price'=>$base,'image_path'=>$imagePath,'prep_minutes'=>$prep,'is_featured'=>$isFeatured,'is_active'=>$isActive]);
            $productId=(int)$pdo->lastInsertId();
        }
        $pdo->commit();
        if ($newPath && $existing && !empty($existing['image_path'])) catalog_delete_uploaded_product_image((string)$existing['image_path']);
        log_activity((int)current_user()['id'],$id>0?'UPDATE_PRODUCT':'CREATE_PRODUCT','products',$productId,['active'=>$isActive,'featured'=>$isFeatured]);
        return ['ok'=>true,'id'=>$productId,'message'=>$id>0?'Product updated.':'Product created. You can now configure sizes and add-ons.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newPath) catalog_delete_uploaded_product_image($newPath);
        throw $e;
    }
}

function admin_toggle_product(int $id, string $field): array
{
    if (!in_array($field,['is_active','is_featured'],true)) return ['ok'=>false,'message'=>'Invalid product action.'];
    $product=admin_product_by_id($id); if(!$product) return ['ok'=>false,'message'=>'Product not found.'];
    $next=(int)$product[$field]===1?0:1;
    db()->prepare("UPDATE products SET {$field}=:value,updated_at=NOW() WHERE id=:id")->execute(['value'=>$next,'id'=>$id]);
    $action=$field==='is_active'?($next?'ENABLE_PRODUCT':'ARCHIVE_PRODUCT'):($next?'FEATURE_PRODUCT':'UNFEATURE_PRODUCT');
    log_activity((int)current_user()['id'],$action,'products',$id);
    return ['ok'=>true,'message'=>$field==='is_active'?($next?'Product is available again.':'Product archived from storefront and POS.'):($next?'Product marked as featured.':'Product removed from featured selections.')];
}

function admin_remove_product_image(int $id): array
{
    $product=admin_product_by_id($id); if(!$product) return ['ok'=>false,'message'=>'Product not found.'];
    $old=(string)($product['image_path']??'');
    db()->prepare('UPDATE products SET image_path=NULL,updated_at=NOW() WHERE id=:id')->execute(['id'=>$id]);
    catalog_delete_uploaded_product_image($old);
    log_activity((int)current_user()['id'],'REMOVE_PRODUCT_IMAGE','products',$id);
    return ['ok'=>true,'message'=>'Product image removed. The storefront will use the placeholder.'];
}

function admin_product_sizes(int $productId): array
{
    $stmt=db()->prepare('SELECT * FROM product_sizes WHERE product_id=:product_id ORDER BY sort_order ASC,price_adjustment ASC,id ASC');
    $stmt->execute(['product_id'=>$productId]); return $stmt->fetchAll();
}

function admin_product_addons(int $productId): array
{
    $stmt=db()->prepare('SELECT * FROM product_addons WHERE product_id=:product_id ORDER BY sort_order ASC,name ASC,id ASC');
    $stmt->execute(['product_id'=>$productId]); return $stmt->fetchAll();
}

function admin_option_by_id(string $type, int $id, int $productId): ?array
{
    $table=$type==='size'?'product_sizes':($type==='addon'?'product_addons':'');
    if ($table==='') return null;
    $stmt=db()->prepare("SELECT * FROM {$table} WHERE id=:id AND product_id=:product_id LIMIT 1");
    $stmt->execute(['id'=>$id,'product_id'=>$productId]); $row=$stmt->fetch(); return $row?:null;
}

function admin_save_product_option(string $type, int $productId, array $data, int $optionId=0): array
{
    if (!admin_product_by_id($productId)) return ['ok'=>false,'errors'=>['Product not found.']];
    $isSize=$type==='size';
    if (!$isSize && $type!=='addon') return ['ok'=>false,'errors'=>['Invalid customization type.']];
    $name=trim((string)($data['name']??''));
    $priceRaw=(string)($data[$isSize?'price_adjustment':'price']??'0');
    $price=filter_var($priceRaw,FILTER_VALIDATE_FLOAT);
    $sort=filter_var($data['sort_order']??0,FILTER_VALIDATE_INT);
    $active=!empty($data['is_active'])?1:0;
    $max=$isSize?50:100;
    $errors=[];
    if ($name==='' || mb_strlen($name)>$max) $errors[]='Customization name is required and is too long.';
    if ($price===false || $price<0 || $price>99999.99) $errors[]='Enter a valid price adjustment.';
    if ($sort===false || $sort<0 || $sort>9999) $errors[]='Sort order must be between 0 and 9999.';
    $table=$isSize?'product_sizes':'product_addons';
    $stmt=db()->prepare("SELECT id FROM {$table} WHERE product_id=:product_id AND LOWER(name)=LOWER(:name) AND id<>:id LIMIT 1");
    $stmt->execute(['product_id'=>$productId,'name'=>$name,'id'=>$optionId]);
    if ($name!=='' && $stmt->fetch()) $errors[]='This product already has a customization with that name.';
    if ($optionId>0 && !admin_option_by_id($type,$optionId,$productId)) $errors[]='Customization not found.';
    if ($errors) return ['ok'=>false,'errors'=>$errors];
    $priceColumn=$isSize?'price_adjustment':'price';
    if ($optionId>0) {
        $stmt=db()->prepare("UPDATE {$table} SET name=:name,{$priceColumn}=:price,sort_order=:sort_order,is_active=:active,updated_at=NOW() WHERE id=:id AND product_id=:product_id");
        $stmt->execute(['name'=>$name,'price'=>$price,'sort_order'=>$sort,'active'=>$active,'id'=>$optionId,'product_id'=>$productId]);
        $id=$optionId;
    } else {
        $stmt=db()->prepare("INSERT INTO {$table} (product_id,name,{$priceColumn},sort_order,is_active) VALUES (:product_id,:name,:price,:sort_order,:active)");
        $stmt->execute(['product_id'=>$productId,'name'=>$name,'price'=>$price,'sort_order'=>$sort,'active'=>$active]);
        $id=(int)db()->lastInsertId();
    }
    log_activity((int)current_user()['id'],$optionId>0?'UPDATE_PRODUCT_OPTION':'CREATE_PRODUCT_OPTION',$table,$id,['product_id'=>$productId]);
    return ['ok'=>true,'id'=>$id,'message'=>($isSize?'Size':'Add-on').' saved.'];
}

function admin_toggle_product_option(string $type, int $productId, int $optionId): array
{
    $row=admin_option_by_id($type,$optionId,$productId);
    if(!$row) return ['ok'=>false,'message'=>'Customization not found.'];
    $table=$type==='size'?'product_sizes':'product_addons';
    $next=(int)$row['is_active']===1?0:1;
    db()->prepare("UPDATE {$table} SET is_active=:active,updated_at=NOW() WHERE id=:id AND product_id=:product_id")->execute(['active'=>$next,'id'=>$optionId,'product_id'=>$productId]);
    log_activity((int)current_user()['id'],$next?'ENABLE_PRODUCT_OPTION':'DISABLE_PRODUCT_OPTION',$table,$optionId,['product_id'=>$productId]);
    return ['ok'=>true,'message'=>($type==='size'?'Size':'Add-on').($next?' enabled.':' disabled.')];
}
