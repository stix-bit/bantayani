<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function saveUploadedImage(array $file, string $subDir, string $prefix): string
{
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException('Only image files are allowed.');
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imageInfo['mime'], $allowedMime, true)) {
        throw new RuntimeException('Unsupported image format uploaded.');
    }

    $uploadRoot = __DIR__ . '/../images/uploads';
    $targetDir = $uploadRoot . '/' . $subDir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to prepare upload directory.');
    }

    $extension = image_type_to_extension($imageInfo[2], false) ?: 'jpg';
    $filename = $prefix . uniqid('', true) . '.' . $extension;
    $targetPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save uploaded image.');
    }

    return 'images/uploads/' . $subDir . '/' . $filename;
}

function deleteUploadedFiles(array $relativePaths): void
{
    foreach ($relativePaths as $relativePath) {
        if ($relativePath === '') {
            continue;
        }

        $absolutePath = __DIR__ . '/../' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

function validateRegistrationInput(array $data, ?array $profileImage, ?array $farmImage, string $activeRole): array
{
    $errors = [];

    if (($data['first_name'] ?? '') === '' || ($data['middle_name'] ?? '') === '' || ($data['last_name'] ?? '') === '' || ($data['email'] ?? '') === '' || ($data['address'] ?? '') === '') {
        $errors[] = 'First name, middle name, last name, email, and address are required.';
    }

    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }

    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    if ($password === '' || $confirmPassword === '') {
        $errors[] = 'Please set and confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z\\d]).{9,}$/', $password)) {
        $errors[] = 'Password must be more than 8 characters and include lowercase, uppercase, number, and symbol.';
    }

    if (!$profileImage || $profileImage['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Profile photo is required.';
    } elseif ($profileImage['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading your profile photo.';
    }

    $allowedPaymentMethods = ['Cash', 'Online'];
    if (!in_array($data['preferred_payment_method'] ?? 'Cash', $allowedPaymentMethods, true)) {
        $errors[] = 'Please choose a valid payment method.';
    }

    if ($activeRole === 'Buyer') {
        if (!in_array($data['buyer_type'] ?? 'Individual', ['Individual', 'Company'], true)) {
            $errors[] = 'Please choose whether you are an individual or company buyer.';
        }

        if (($data['buyer_type'] ?? 'Individual') === 'Company') {
            if (trim($data['company_name'] ?? '') === '' || trim($data['company_address'] ?? '') === '' || trim($data['contact_person'] ?? '') === '' || trim($data['tax_id'] ?? '') === '') {
                $errors[] = 'Company name, address, contact person, and tax ID are required for company buyers.';
            }
            
            // Validate company name length
            if (strlen(trim($data['company_name'] ?? '')) > 100) {
                $errors[] = 'Company name must be 100 characters or less.';
            }
            
            // Validate tax ID format and length
            if (strlen(trim($data['tax_id'] ?? '')) > 50) {
                $errors[] = 'Tax ID must be 50 characters or less.';
            }
        }
    }

    if ($activeRole === 'Farmer') {
        if (trim($data['farm_name'] ?? '') === '' || trim($data['farm_location'] ?? '') === '') {
            $errors[] = 'Farm name and farm location are required for farmers.';
        }

        if ($farmImage && $farmImage['error'] !== UPLOAD_ERR_OK && $farmImage['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Farm image upload failed. Please try again.';
        }
    }

    return $errors;
}

function emailAlreadyExists(mysqli $conn, string $email): bool
{
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function saveOptionalImage(?array $file, string $subDir, string $prefix): ?string
{
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One of the optional images failed to upload.');
    }

    return saveUploadedImage($file, $subDir, $prefix);
}

function createUserWithProfiles(
    mysqli $conn,
    string $role,
    array $data,
    string $profilePath,
    ?string $farmImagePath
): void {
    mysqli_begin_transaction($conn);

    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
    $insertUserStmt = $conn->prepare('INSERT INTO users (role, first_name, middle_name, last_name, email, password, contact_number, address, img_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertUserStmt->bind_param('sssssssss', $role, $data['first_name'], $data['middle_name'], $data['last_name'], $data['email'], $hashedPassword, $data['contact_number'], $data['address'], $profilePath);
    $insertUserStmt->execute();
    $userId = $conn->insert_id;
    $insertUserStmt->close();

    if ($role === 'Buyer') {
        $buyerStmt = $conn->prepare('INSERT INTO buyer_profiles (buyer_id, preferred_payment_method, verified) VALUES (?, ?, 0)');
        $buyerStmt->bind_param('is', $userId, $data['preferred_payment_method']);
        $buyerStmt->execute();
        $buyerStmt->close();

        if ($data['buyer_type'] === 'Company') {
            // Check if company already exists by tax_id or company_name
            $checkCompanyStmt = $conn->prepare('SELECT company_id FROM companies WHERE tax_id = ? OR company_name = ? LIMIT 1');
            $checkCompanyStmt->bind_param('ss', $data['tax_id'], $data['company_name']);
            $checkCompanyStmt->execute();
            $checkCompanyStmt->store_result();
            
            if ($checkCompanyStmt->num_rows > 0) {
                $checkCompanyStmt->bind_result($existingCompanyId);
                $checkCompanyStmt->fetch();
                $companyId = $existingCompanyId;
                $checkCompanyStmt->close();
            } else {
                $checkCompanyStmt->close();
                // Insert new company
                $companyStmt = $conn->prepare('INSERT INTO companies (company_name, company_address, contact_person, tax_id) VALUES (?, ?, ?, ?)');
                $companyStmt->bind_param('ssss', $data['company_name'], $data['company_address'], $data['contact_person'], $data['tax_id']);
                $companyStmt->execute();
                $companyId = $conn->insert_id;
                $companyStmt->close();
            }
            
            // Link buyer to company
            $companyBuyerStmt = $conn->prepare('INSERT INTO company_buyers (company_id, buyer_id, role_in_company) VALUES (?, ?, ?)');
            $roleInCompany = 'Employee'; // Default role, can be enhanced later
            $companyBuyerStmt->bind_param('iis', $companyId, $userId, $roleInCompany);
            $companyBuyerStmt->execute();
            $companyBuyerStmt->close();
        }
    } else {
        $farmerStmt = $conn->prepare('INSERT INTO farmer_profiles (farmer_id, farm_name, farm_location, farm_img_path) VALUES (?, ?, ?, ?)');
        $farmerStmt->bind_param('isss', $userId, $data['farm_name'], $data['farm_location'], $farmImagePath);
        $farmerStmt->execute();
        $farmerStmt->close();
    }

    mysqli_commit($conn);
}

$allowedRoles = ['Buyer', 'Farmer'];
$roleFromRequest = $_GET['role'] ?? ($_POST['role'] ?? '');

if (!in_array($roleFromRequest, $allowedRoles, true)) {
    header('Location: register-choice.php');
    exit;
}

$activeRole = $roleFromRequest;
$buyerType = $_POST['buyer_type'] ?? 'Individual';
$buyerType = in_array($buyerType, ['Individual', 'Company'], true) ? $buyerType : 'Individual';

$errors = [];
$successMessage = '';
$profileImageRelativePath = '';
$farmImageRelativePath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activeRole = $_POST['role'] ?? $activeRole;
    if (!in_array($activeRole, $allowedRoles, true)) {
        $errors[] = 'Invalid registration role selected.';
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $buyerType = $_POST['buyer_type'] ?? 'Individual';
    $buyerType = in_array($buyerType, ['Individual', 'Company'], true) ? $buyerType : 'Individual';
    $preferredPaymentMethod = $_POST['preferred_payment_method'] ?? 'Cash';
    $preferredPaymentMethod = in_array($preferredPaymentMethod, ['Cash', 'Online'], true) ? $preferredPaymentMethod : 'Cash';
    $companyName = trim($_POST['company_name'] ?? '');
    $companyAddress = trim($_POST['company_address'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $taxId = trim($_POST['tax_id'] ?? '');
    $farmName = trim($_POST['farm_name'] ?? '');
    $farmLocation = trim($_POST['farm_location'] ?? '');
    $profileImage = $_FILES['profile_image'] ?? null;
    $farmImage = $_FILES['farm_image'] ?? null;

    $formData = [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'email' => $email,
        'password' => $password,
        'confirm_password' => $confirmPassword,
        'contact_number' => $contactNumber,
        'address' => $address,
        'preferred_payment_method' => $preferredPaymentMethod,
        'buyer_type' => $buyerType,
        'company_name' => $companyName,
        'company_address' => $companyAddress,
        'contact_person' => $contactPerson,
        'tax_id' => $taxId,
        'farm_name' => $farmName,
        'farm_location' => $farmLocation,
    ];

    $errors = array_merge($errors, validateRegistrationInput($formData, $profileImage, $farmImage, $activeRole));

    if (empty($errors) && emailAlreadyExists($conn, $email)) {
        $errors[] = 'Email is already registered. Please log in instead.';
    }

    $uploadedRelativePaths = [];

    if (empty($errors)) {
        try {
            $profileImageRelativePath = saveUploadedImage($profileImage, 'profiles', 'profile_');
            $uploadedRelativePaths[] = $profileImageRelativePath;

            if ($activeRole === 'Farmer') {
                $farmImageRelativePath = saveOptionalImage($farmImage, 'farms', 'farm_');
                if ($farmImageRelativePath !== null) {
                    $uploadedRelativePaths[] = $farmImageRelativePath;
                }
            }
        } catch (RuntimeException $uploadException) {
            $errors[] = $uploadException->getMessage();
            deleteUploadedFiles($uploadedRelativePaths);
            $uploadedRelativePaths = [];
            $profileImageRelativePath = '';
            $farmImageRelativePath = null;
        }
    }

    if (empty($errors)) {
        try {
            $formData['preferred_payment_method'] = $preferredPaymentMethod;
            createUserWithProfiles($conn, $activeRole, $formData, $profileImageRelativePath, $farmImageRelativePath);
            $_SESSION['registration_success'] = 'Registration successful! You may now log in.';
            header('Location: login.php');
            exit;
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            deleteUploadedFiles($uploadedRelativePaths);
            
            // Handle specific duplicate company errors
            if (strpos($exception->getMessage(), 'Duplicate entry') !== false && strpos($exception->getMessage(), 'company_name') !== false) {
                $errors[] = 'A company with this name already exists. Please contact your administrator or use a different company name.';
            } elseif (strpos($exception->getMessage(), 'Duplicate entry') !== false && strpos($exception->getMessage(), 'tax_id') !== false) {
                $errors[] = 'A company with this Tax ID already exists. Please verify your Tax ID or contact your administrator.';
            } else {
                $errors[] = 'An unexpected error occurred during registration. Please try again later.';
            }
        }
    } elseif (!empty($uploadedRelativePaths)) {
        deleteUploadedFiles($uploadedRelativePaths);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BantayAni | Register as <?= htmlspecialchars($activeRole, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');
        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige: #f6f1e9;
            --orange: #f28705;
            --text: #1f2933;
            --error: #b91c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Quicksand', 'Segoe UI', sans-serif;
            background: linear-gradient(130deg, rgba(12, 92, 76, 0.07), rgba(242, 135, 5, 0.12));
            color: var(--text);
        }

        .page {
            max-width: 960px;
            margin: 0 auto;
            padding: 48px 16px 64px;
        }

        h1 {
            text-align: center;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--green-dark);
        }

        .subtitle {
            text-align: center;
            margin: 0 0 32px;
            color: #4c5662;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(12, 92, 76, 0.12);
            color: var(--green-dark);
            font-weight: 600;
            margin-bottom: 24px;
        }

        .change-role-link {
            display: inline-block;
            margin-bottom: 32px;
            color: var(--green);
            text-decoration: none;
            font-weight: 600;
        }

        form {
            background: white;
            padding: 32px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(12, 92, 76, 0.15);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .grid.password-grid {
            margin-top: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .field-note {
            margin-top: 4px;
            font-size: 0.9rem;
            color: #5f6b79;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #d6dbe1;
            font-size: 1rem;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        .section-card {
            margin-top: 32px;
            padding: 24px;
            border-radius: 20px;
            background: var(--beige);
        }

        .section-card h3 {
            margin-top: 0;
            color: var(--green-dark);
        }

        .file-input {
            border: 1px dashed #c7d0d9;
            padding: 20px;
            border-radius: 16px;
            background: white;
        }

        .submit-row {
            margin-top: 32px;
            text-align: right;
        }

        button[type="submit"] {
            background: var(--green);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 999px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 150ms ease, transform 150ms ease;
        }

        button[type="submit"]:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .alert-error {
            background: rgba(185, 28, 28, 0.1);
            border: 1px solid rgba(185, 28, 28, 0.4);
            color: var(--error);
        }

        .alert-success {
            background: rgba(31, 138, 112, 0.1);
            border: 1px solid rgba(31, 138, 112, 0.4);
            color: var(--green-dark);
        }

        @media (max-width: 640px) {
            form {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="role-pill">Registering as <?= htmlspecialchars($activeRole, ENT_QUOTES, 'UTF-8'); ?></div>
    <h1>Creating Your BantayAni Account</h1>
    <p class="subtitle">Share a few details so we can personalize your <?= strtolower($activeRole); ?> experience.</p>
    <a class="change-role-link" href="register-choice.php">&larr; Choose a different role</a>

    <?php if (!empty($errors)) : ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($successMessage !== '') : ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="role" value="<?= htmlspecialchars($activeRole, ENT_QUOTES, 'UTF-8'); ?>" />

        <div class="grid">
            <div>
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div>
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($_POST['middle_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div>
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div>
                <label for="email">Email</label>
                <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        required
                        oninvalid="this.setCustomValidity('Invalid email')"
                        oninput="this.setCustomValidity('')"
                />
            </div>
            <div>
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($_POST['contact_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
        </div>

        <div class="grid password-grid">
            <div>
                <label for="password">Password</label>
                <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="9"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{9,}"
                        title="At least 9 chars with lowercase, uppercase, number, and symbol"
                        required
                />
                <p class="field-note">Use 9+ characters with lowercase, uppercase, number, and symbol.</p>
            </div>
            <div>
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="9" required />
            </div>
        </div>

        <div style="margin-top: 20px;">
            <label for="address">Address</label>
            <textarea id="address" name="address" required><?= htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="section-card">
            <h3>Profile Photo</h3>
            <div class="file-input">
                <input type="file" name="profile_image" id="profile_image" accept="image/*" required />
                <p class="field-note">Upload a clear headshot (JPG, PNG, GIF, WEBP).</p>
            </div>
        </div>

        <?php if ($activeRole === 'Buyer') : ?>
            <div class="section-card">
                <h3>Buyer Details</h3>
                <div class="grid">
                    <div>
                        <label for="buyer_type">Buyer Type</label>
                        <select name="buyer_type" id="buyer_type" required>
                            <option value="Individual" <?= $buyerType === 'Individual' ? 'selected' : ''; ?>>Individual</option>
                            <option value="Company" <?= $buyerType === 'Company' ? 'selected' : ''; ?>>Company</option>
                        </select>
                    </div>
                    <div>
                        <label for="preferred_payment_method">Preferred Payment Method</label>
                        <select name="preferred_payment_method" id="preferred_payment_method" required>
                            <option value="Cash" <?= ($_POST['preferred_payment_method'] ?? 'Cash') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Online" <?= ($_POST['preferred_payment_method'] ?? '') === 'Online' ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                </div>

                <div id="companyFields" class="grid" style="margin-top: 20px;">
                    <div>
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div>
                        <label for="company_address">Company Address</label>
                        <input type="text" id="company_address" name="company_address" value="<?= htmlspecialchars($_POST['company_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div>
                        <label for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($_POST['contact_person'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div>
                        <label for="tax_id">Tax ID</label>
                        <input type="text" id="tax_id" name="tax_id" value="<?= htmlspecialchars($_POST['tax_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div style="grid-column: span 2;">
                        <p class="field-note">Company details are required if you are registering as a company buyer.</p>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="section-card">
                <h3>Farm Details</h3>
                <div class="grid">
                    <div>
                        <label for="farm_name">Farm Name</label>
                        <input type="text" id="farm_name" name="farm_name" value="<?= htmlspecialchars($_POST['farm_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>
                    <div>
                        <label for="farm_location">Farm Location</label>
                        <input type="text" id="farm_location" name="farm_location" value="<?= htmlspecialchars($_POST['farm_location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>
                </div>
                <div class="file-input" style="margin-top: 20px;">
                    <label for="farm_image">Farm Photo (optional)</label>
                    <input type="file" id="farm_image" name="farm_image" accept="image/*" />
                    <p class="field-note">Upload one image to highlight your farm. JPG/PNG/GIF/WEBP accepted.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="submit-row">
            <button type="submit">Create Account</button>
        </div>
    </form>
</div>

<?php if ($activeRole === 'Buyer') : ?>
    <script>
        const buyerTypeField = document.getElementById('buyer_type');
        const companyFields = document.getElementById('companyFields');
        const companyInputs = companyFields.querySelectorAll('input');

        const toggleCompanyFields = () => {
            const isCompany = buyerTypeField.value === 'Company';
            companyFields.style.display = isCompany ? 'grid' : 'none';
            companyInputs.forEach((input) => {
                input.required = isCompany;
                if (!isCompany && input.type === 'text') {
                    input.value = input.value;
                }
            });
        };

        buyerTypeField.addEventListener('change', toggleCompanyFields);
        toggleCompanyFields();
    </script>
<?php endif; ?>
</body>
</html>
