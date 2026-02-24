<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if farmer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Farmer') {
    header('Location: ../user/login.php');
    exit;
}

$farmer_id = $_SESSION['user_id'];

// Fetch crop categories
$categories_query = "SELECT category_id, category_name FROM crop_categories ORDER BY display_order, category_name";
$categories_result = $conn->query($categories_query);

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $crop_id = intval($_POST['crop_id'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit = $_POST['unit'] ?? 'kg';
    $price = floatval($_POST['price'] ?? 0);
    $harvest_date = $_POST['harvest_date'] ?? '';

    // Validation
    if ($category_id <= 0) $errors[] = 'Please select a crop category.';
    if ($crop_id <= 0) $errors[] = 'Please select a crop.';
    if ($quantity <= 0) $errors[] = 'Quantity must be greater than 0.';
    if (!in_array($unit, ['kg', 'g', 'pieces', 'sack', 'bundle'])) $errors[] = 'Invalid unit selected.';
    if ($price < 0) $errors[] = 'Price cannot be negative.';
    if ($harvest_date === '') $errors[] = 'Harvest date is required.';

    // Handle image uploads
    $uploaded_images = [];
    if (isset($_FILES['crop_images']) && !empty($_FILES['crop_images']['name'][0])) {
        $upload_dir = __DIR__ . '/../images/uploads/crops/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($_FILES['crop_images']['name'] as $key => $name) {
            if ($_FILES['crop_images']['error'][$key] === UPLOAD_ERR_OK) {
                $image_info = getimagesize($_FILES['crop_images']['tmp_name'][$key]);
                if ($image_info !== false) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($image_info['mime'], $allowed_types)) {
                        $extension = pathinfo($name, PATHINFO_EXTENSION);
                        $filename = 'crop_' . uniqid() . '.' . $extension;
                        $target_path = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['crop_images']['tmp_name'][$key], $target_path)) {
                            $uploaded_images[] = [
                                'path' => 'images/uploads/crops/' . $filename,
                                'is_primary' => $key === 0 ? 1 : 0
                            ];
                        }
                    }
                }
            }
        }
        if (empty($uploaded_images)) $errors[] = 'Failed to upload images. Please check file formats.';
    }

    if (empty($errors)) {
        try {
            // Insert into crops_inventory
            $stmt = $conn->prepare("
                INSERT INTO crops_inventory 
                (farmer_id, crop_id, quantity, unit, price, harvest_date) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iisdss', $_SESSION['user_id'], $crop_id, $quantity, $unit, $price, $harvest_date);
            $stmt->execute();
            $inventory_id = $conn->insert_id;
            $stmt->close();

            // Insert images
            if (!empty($uploaded_images)) {
                $stmt = $conn->prepare("INSERT INTO crop_images (inventory_id, image_path, is_primary) VALUES (?, ?, ?)");
                foreach ($uploaded_images as $img) {
                    $stmt->bind_param('isi', $inventory_id, $img['path'], $img['is_primary']);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $_SESSION['success_message'] = 'Crop added successfully to inventory!';
            header('Location: inventory.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Crop | BantayAni</title>
<style>
/* All styles same as before */
:root {--green-dark:#0c5c4c;--green:#1f8a70;--beige:#f6f1e9;--orange:#f28705;--text:#1f2933;}
*{box-sizing:border-box;}
body{margin:0;font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background:linear-gradient(130deg, rgba(12,92,76,0.07), rgba(242,135,5,0.12));color:var(--text);min-height:100vh;}
.container{max-width:960px;margin:0 auto;padding:40px 16px;}
.header{background:white;padding:24px;border-radius:16px;box-shadow:0 8px 24px rgba(12,92,76,0.15);margin-bottom:24px;}
.header h1{margin:0 0 8px;color:var(--green-dark);}
.header p{margin:0;color:#4c5662;}
.form-card{background:white;padding:32px;border-radius:16px;box-shadow:0 12px 32px rgba(12,92,76,0.15);}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:24px;}
.form-group{display:flex;flex-direction:column;}
label{font-weight:600;margin-bottom:6px;color:var(--green-dark);}
input,select,textarea{padding:12px 14px;border:1px solid #d6dbe1;border-radius:8px;font-size:1rem;font-family:inherit;}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(31,138,112,0.1);}
.file-input{border:2px dashed #c7d0d9;padding:20px;border-radius:12px;text-align:center;background:var(--beige);}
.image-preview-container{display:flex;flex-wrap:wrap;gap:10px;margin-top:15px;justify-content:center;}
.image-preview{position:relative;width:80px;height:80px;border-radius:8px;overflow:hidden;border:2px solid #e4e7eb;}
.image-preview img{width:100%;height:100%;object-fit:cover;}
.image-preview .remove-btn{position:absolute;top:-5px;right:-5px;background:#ef4444;color:white;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;}
.image-preview .primary-badge{position:absolute;top:2px;left:2px;background:var(--green);color:white;font-size:10px;padding:2px 4px;border-radius:4px;font-weight:bold;}
.btn{background:var(--green);color:white;border:none;padding:14px 28px;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:all 0.2s ease;}
.btn:hover{background:var(--green-dark);transform:translateY(-1px);}
.alert{padding:16px;border-radius:12px;margin-bottom:24px;}
.alert-error{background:rgba(185,28,28,0.1);border:1px solid rgba(185,28,28,0.4);color:#b91c1c;}
.alert-success{background:rgba(31,138,112,0.1);border:1px solid rgba(31,138,112,0.4);color:var(--green-dark);}
.nav-link{color:var(--green);text-decoration:none;font-weight:600;display:inline-block;margin-bottom:16px;}
.nav-link:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class="container">
<div class="header">
<h1>Add Crop to Inventory</h1>
<p>Add your harvested crops to make them available for buyers.</p>
<a href="inventory.php" class="nav-link">← Back to Inventory</a>
</div>

<?php if(!empty($errors)): ?>
<div class="alert alert-error">
<strong>Error:</strong>
<ul><?php foreach($errors as $err) echo "<li>".htmlspecialchars($err)."</li>"; ?></ul>
</div>
<?php endif; ?>

<div class="form-card">
<form method="POST" enctype="multipart/form-data">
<div class="form-grid">

<div class="form-group">
<label for="category_id">Crop Category</label>
<select id="category_id" name="category_id" required>
<option value="">Select Category</option>
<?php while($cat = $categories_result->fetch_assoc()): ?>
<option value="<?= $cat['category_id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id']==$cat['category_id'])?'selected':''; ?>>
<?= htmlspecialchars($cat['category_name']); ?>
</option>
<?php endwhile; ?>
</select>
</div>

<div class="form-group">
<label for="crop_id">Crop</label>
<select id="crop_id" name="crop_id" required>
<option value="">Select Crop</option>
</select>
</div>

<div class="form-group">
<label for="quantity">Quantity</label>
<input type="number" id="quantity" name="quantity" step="0.01" min="0.01" value="<?= htmlspecialchars($_POST['quantity'] ?? ''); ?>" required>
</div>

<div class="form-group">
<label for="unit">Unit</label>
<select id="unit" name="unit" required>
<option value="kg" <?= (isset($_POST['unit'])&&$_POST['unit']==='kg')?'selected':''; ?>>Kilograms (kg)</option>
<option value="g" <?= (isset($_POST['unit'])&&$_POST['unit']==='g')?'selected':''; ?>>Grams (g)</option>
<option value="pieces" <?= (isset($_POST['unit'])&&$_POST['unit']==='pieces')?'selected':''; ?>>Pieces</option>
<option value="sack" <?= (isset($_POST['unit'])&&$_POST['unit']==='sack')?'selected':''; ?>>Sack</option>
<option value="bundle" <?= (isset($_POST['unit'])&&$_POST['unit']==='bundle')?'selected':''; ?>>Bundle</option>
</select>
</div>

<div class="form-group">
<label for="price">Price per Unit (₱)</label>
<input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price'] ?? ''); ?>" required>
</div>

<div class="form-group">
<label for="harvest_date">Harvest Date</label>
<input type="date" id="harvest_date" name="harvest_date" value="<?= htmlspecialchars(!empty($_POST['harvest_date'])?date('Y-m-d', strtotime($_POST['harvest_date'])):''); ?>" required>
</div>

</div>

<div class="form-group">
<label>Crop Photos (Optional)</label>
<div class="file-input">
<input type="file" name="crop_images[]" id="crop_images" accept="image/*" multiple>
<p>Upload up to 5 photos (JPG, PNG, GIF, WEBP). First image will be primary.</p>
<div id="image-preview" class="image-preview-container"></div>
</div>
</div>

<button type="submit" class="btn">Add to Inventory</button>
</form>
</div>
</div>

<script>
// Image preview code (same as before)
document.getElementById('crop_images').addEventListener('change', function(e) {
    const container = document.getElementById('image-preview');
    container.innerHTML = '';
    const files = Array.from(e.target.files).slice(0,5);
    files.forEach((file,index)=>{
        if(file.type.startsWith('image/')){
            const reader = new FileReader();
            reader.onload = function(ev){
                const div = document.createElement('div'); div.className='image-preview';
                const img = document.createElement('img'); img.src=ev.target.result;
                const btn = document.createElement('button'); btn.className='remove-btn'; btn.innerHTML='×';
                btn.onclick=function(){div.remove();};
                if(index===0){ const badge = document.createElement('div'); badge.className='primary-badge'; badge.textContent='PRIMARY'; div.appendChild(badge); }
                div.appendChild(img); div.appendChild(btn); container.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    });
});

// AJAX to load crops based on category
document.getElementById('category_id').addEventListener('change', function() {
    const categoryId = this.value;
    const cropSelect = document.getElementById('crop_id');
    cropSelect.innerHTML = '<option value="">Loading...</option>';

    fetch('get_crops.php?category_id='+categoryId)
    .then(res => res.json())
    .then(data => {
        cropSelect.innerHTML = '<option value="">Select Crop</option>';
        data.forEach(crop=>{
            const opt = document.createElement('option');
            opt.value = crop.crop_id;
            opt.textContent = crop.crop_name;
            cropSelect.appendChild(opt);
        });
    });
});
</script>
</body>
</html>