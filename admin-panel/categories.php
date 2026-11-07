<?php
declare(strict_types=1);
require_once __DIR__.'/../shared-components/auth.php';
auth_require_admin();
$pdo=eventnest_db();
if($_SERVER['REQUEST_METHOD']==='POST') {
    eventnest_require_post();
    $id=eventnest_id($_POST['category_id'] ?? null);
    $action=trim_input($_POST['action'] ?? '');
    $name=trim_input($_POST['name'] ?? '');
    $description=trim_input($_POST['description'] ?? '');
    $saved_image=null;
    $old_image=null;
    try {
        $pdo->beginTransaction();
        if(in_array($action,['edit','delete'],true)) {
            $stmt=$pdo->prepare('SELECT ImagePath FROM CATEGORIES WHERE CategoryID=? FOR UPDATE');
            $stmt->execute([$id]);
            $current_image=$stmt->fetch(PDO::FETCH_NUM);
            if(!$current_image) {
                throw new DomainException('Category not found.');
            }
        }
        if($action==='delete') {
            // Check every approved category foreign key before attempting deletion.
            $stmt=$pdo->prepare('SELECT (SELECT COUNT(*) FROM EVENTS WHERE CategoryID=? OR AllowedCareerField=?) + (SELECT COUNT(*) FROM Post_Categories WHERE category_id=?)');
            $stmt->execute([$id,$id,$id]);
            if($stmt->fetchColumn()) {
                throw new DomainException('This category is used by events, career gates or posts. Reassign them first.');
            }
            $stmt=$pdo->prepare('DELETE FROM CATEGORIES WHERE CategoryID=?');
            $stmt->execute([$id]);
            $old_image=$current_image[0];
        }
        elseif(in_array($action,['create','edit'],true)) {
            if($name==='' || mb_strlen($name)>100 || mb_strlen($description)>5000) {
                throw new DomainException('Enter a category name up to 100 characters and description up to 5,000.');
            }
            $stmt=$pdo->prepare('SELECT CategoryID FROM CATEGORIES WHERE CategoryName=? AND CategoryID<>?');
            $stmt->execute([$name,$id]);
            if($stmt->fetchColumn()) {
                throw new DomainException('That category name already exists.');
            }
            $upload=eventnest_save_image_upload(is_array($_FILES['category_image'] ?? null)?$_FILES['category_image']:null,'category-images','category');
            $saved_image=$upload['absolute'] ?? null;
            if($action==='create') {
                $stmt=$pdo->prepare('INSERT INTO CATEGORIES (CategoryName,Description,ImagePath) VALUES (?,?,?)');
                $stmt->execute([$name,$description ?: null,$upload['relative'] ?? null]);
            }
            else {
                // Existing career-field preferences use text; preserve their category meaning on rename.
                $stmt=$pdo->prepare('SELECT CategoryName FROM CATEGORIES WHERE CategoryID=?');
                $stmt->execute([$id]);
                $old_name=$stmt->fetchColumn();
                $image_path=$upload['relative'] ?? $current_image[0];
                if(!$upload && isset($_POST['remove_image'])) {
                    $image_path=null;
                }
                if($image_path!==$current_image[0]) {
                    $old_image=$current_image[0];
                }
                $stmt=$pdo->prepare('UPDATE CATEGORIES SET CategoryName=?,Description=?,ImagePath=? WHERE CategoryID=?');
                $stmt->execute([$name,$description ?: null,$image_path,$id]);
                $stmt=$pdo->prepare('UPDATE USERS SET CareerField=? WHERE CareerField=?');
                $stmt->execute([$name,$old_name]);
                $pdo->exec('DELETE FROM USER_EVENT_RECOMMENDATIONS');
            }
        }
        else {
            throw new DomainException('Invalid category action.');
        }
        $pdo->commit();
        if($old_image) {
            eventnest_delete_upload($old_image);
        }
        auth_set_flash('Category saved.','success');
    }
    catch(Throwable $exception) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if($saved_image && is_file($saved_image)) {
            @unlink($saved_image);
        }
        auth_set_flash($exception instanceof DomainException?$exception->getMessage():'Unable to change category. It may be referenced or have a duplicate name.');
    }
    auth_redirect('admin-panel/categories.php');
}
$categories=$pdo->query('SELECT c.*,(SELECT COUNT(*) FROM EVENTS e WHERE e.CategoryID=c.CategoryID OR e.AllowedCareerField=c.CategoryID) AS EventUsage,(SELECT COUNT(*) FROM Post_Categories pc WHERE pc.category_id=c.CategoryID) AS PostUsage FROM CATEGORIES c ORDER BY c.CategoryName')->fetchAll();
$page_title='Career-field categories';
require __DIR__.'/../shared-components/header.php';
?>
<div class="dashboard-layout"><?php eventnest_sidebar('admin'); ?><main class="dashboard-main"><h1>Career-field categories</h1><form class="content-card form-grid" method="post" enctype="multipart/form-data"><?php eventnest_csrf_field(); ?><input type="hidden" name="action" value="create"><div class="field"><label for="category-name">Category name</label><input id="category-name" name="name" maxlength="100" required></div><div class="field"><label for="category-description">Description</label><input id="category-description" name="description" maxlength="5000"></div><div class="field"><label for="category-image">Category image (optional)</label><input id="category-image" type="file" name="category_image" accept=".jpg,.jpeg,.png,.webp" data-media-preview><small>JPG, PNG or WebP, up to 5 MB.</small><div data-upload-preview></div></div><button class="btn btn-orange">Add category</button></form>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Category / description</th><th>Usage</th><th>Actions</th></tr></thead><tbody><?php foreach($categories as $category): ?><tr><td><?= (int)$category['CategoryID'] ?></td><td><form id="edit-<?= (int)$category['CategoryID'] ?>" method="post" enctype="multipart/form-data"><?php eventnest_csrf_field(); ?><input type="hidden" name="category_id" value="<?= (int)$category['CategoryID'] ?>"><label>Category name<input name="name" maxlength="100" value="<?= escape_html($category['CategoryName']) ?>" required></label><label>Description<textarea name="description" maxlength="5000"><?= escape_html($category['Description']) ?></textarea></label><?php if(!empty($category['ImagePath'])): ?><img class="event-card__image" style="max-width:200px" src="<?= escape_html(eventnest_image_url($category['ImagePath'], 'category')) ?>" alt="Current image for <?= escape_html($category['CategoryName']) ?>"><label><input type="checkbox" name="remove_image" value="1"> Remove image</label><?php endif; ?><label>Category image<input type="file" name="category_image" accept=".jpg,.jpeg,.png,.webp"></label><button class="btn btn-blue" name="action" value="edit">Save</button></form></td><td><?= (int)$category['EventUsage'] ?> event/gate references; <?= (int)$category['PostUsage'] ?> post references</td><td><form method="post" data-confirm="Delete this unused category?"><?php eventnest_csrf_field(); ?><input type="hidden" name="category_id" value="<?= (int)$category['CategoryID'] ?>"><button name="action" value="delete" class="btn btn-red" <?= $category['EventUsage'] || $category['PostUsage']?'disabled':'' ?>>Delete</button></form><a href="../events.php?category_id=<?= (int)$category['CategoryID'] ?>">View events</a></td></tr><?php endforeach; ?></tbody></table></div></main></div>
<?php require __DIR__.'/../shared-components/footer.php'; ?>
