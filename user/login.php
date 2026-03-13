<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$errors = [];
$info_message = trim($_GET['message'] ?? '');
$next = trim($_GET['next'] ?? '');
$safe_next = '';
if ($next !== '' && str_starts_with($next, '/bantayani/')) {
    $safe_next = $next;
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? null;
    if ($safe_next !== '') {
        header('Location: ' . $safe_next);
        exit;
    }
    if ($role === 'Admin') {
        header('Location: /bantayani/admin/index.php');
        exit;
    }
    header('Location: /bantayani/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT user_id, role, password, first_name FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];

            if ($safe_next !== '') {
                header('Location: ' . $safe_next);
                exit;
            }

            if ($user['role'] === 'Admin') {
                header('Location: /bantayani/admin/index.php');
                exit;
            }

            header('Location: /bantayani/index.php');
            exit;
        }

        $errors[] = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BantayAni | Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap');

        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige: #f6f1e9;
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
            background: linear-gradient(135deg, rgba(12, 92, 76, 0.08), rgba(242, 135, 5, 0.15));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .page-grid {
            display: grid;
            grid-template-columns: minmax(320px, 1fr) minmax(420px, 1.45fr);
            justify-items: center;
            gap: 30px;
            width: min(1100px, 100%);
            align-items: start;
        }

        .card,
        .about-card {
            width: 100%;
            max-width: 100%;
            background: white;
            border-radius: 28px;
            padding: 42px 36px;
            box-shadow: 0 25px 60px rgba(12, 92, 76, 0.2);
        }

        .card {
            max-width: 420px;
            margin: 0 auto;
        }

        .about-card {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid rgba(31, 138, 112, 0.18);
        }

        .about-card h2 {
            margin-top: 0;
            color: var(--green-dark);
            font-size: 1.7rem;
        }

        .about-card img {
            display: block;
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin-bottom: 20px;
        }

        .about-card p {
            color: #334149;
            line-height: 1.55;
            margin: 0 0 14px;
            text-align: justify;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.8rem;
            color: var(--green-dark);
            text-align: center;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.8rem;
            color: var(--green-dark);
            text-align: center;
        }

        p.subtitle {
            text-align: center;
            margin: 0 0 32px;
            color: #4c5662;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #d8dee6;
            font-size: 1rem;
            margin-bottom: 20px;
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px 16px;
            border-radius: 999px;
            border: none;
            background: var(--green);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 120ms ease, background 120ms ease;
        }

        button[type="submit"]:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid rgba(185, 28, 28, 0.4);
            background: rgba(185, 28, 28, 0.1);
            color: var(--error);
            margin-bottom: 24px;
        }

        .switch-link {
            text-align: center;
            margin-top: 20px;
            color: #4c5662;
        }

        .switch-link a {
            color: var(--green);
            font-weight: 600;
            text-decoration: none;
        }

        @media (max-width: 900px) {
            .page-grid {
                grid-template-columns: 1fr;
            }

            .card,
            .about-card {
                padding: 28px 22px;
            }
        }

        @media (max-width: 480px) {
            .card,
            .about-card {
                padding: 22px 16px;
            }

            body {
                padding: 16px;
            }

            h1 {
                font-size: 1.5rem;
            }

            .about-card h2 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
<div class="page-grid">
    <div class="card">
        <h1>Welcome!</h1>
        <p class="subtitle">Sign in to continue your BantayAni journey.</p>

        <?php if ($info_message !== '') : ?>
            <div class="alert">
            <ul>
                <li><?= htmlspecialchars($info_message, ENT_QUOTES, 'UTF-8'); ?></li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <div class="alert">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required />

        <button type="submit">Log In</button>
    </form>

    <p class="switch-link">Don’t have an account? <a href="register-choice.php">Create one</a>.</p>
</div>

    <aside class="about-card">
        <img src="../images/logo.png" alt="BantayAni logo" />
        <h2>About BantayAni</h2>
        <p>BANTAY-ANI is a farm-to-market trading and logistics platform designed to support small-scale farmers, buyers, and local agricultural authorities through a centralized digital system. Our mission is to improve transparency, efficiency, and connectivity within the agricultural supply chain by providing tools that simplify production monitoring, market access, and delivery coordination.</p>
        <p>Many farmers still rely on traditional methods for managing harvests, communicating with buyers, and tracking transactions. These manual processes often limit their access to markets and reduce their bargaining power. BANTAY-ANI addresses these challenges by offering an integrated online platform where farmers can manage crop inventory, schedule harvests, coordinate deliveries, and connect directly with verified buyers.</p>
        <p>At its core, BANTAY-ANI aims to empower farmers, strengthen local agriculture, and create a more transparent and sustainable farm-to-market ecosystem for communities.</p>
        <p><strong>Created using PHP, HTML/CSS, Javascript, MySQL</strong></p>
    </aside>
</div>
</body>
</html>
