<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>BantayAni | Choose your role</title>
    <style>
        :root {
            --green-dark: #0c5c4c;
            --green: #1f8a70;
            --beige: #f6f1e9;
            --orange: #f28705;
            --text: #1f2933;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(120deg, rgba(12, 92, 76, 0.12), rgba(242, 135, 5, 0.12));
            color: var(--text);
        }

        .page {
            max-width: 960px;
            margin: 0 auto;
            padding: 60px 16px 80px;
        }

        h1 {
            text-align: center;
            color: var(--green-dark);
            margin-bottom: 8px;
        }

        p.subtitle {
            text-align: center;
            color: #4c5662;
            margin-bottom: 40px;
        }

        .card-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 16px 36px rgba(12, 92, 76, 0.15);
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
            transition: transform 180ms ease, border 180ms ease;
        }

        .card:hover,
        .card:focus-visible {
            border-color: var(--green);
            transform: translateY(-4px);
        }

        .card h2 {
            margin: 0 0 12px;
            color: var(--green-dark);
        }

        .card p {
            margin: 0 0 20px;
            color: #4c5662;
            line-height: 1.4;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(12, 92, 76, 0.08);
            color: var(--green-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 30px;
            padding: 10px 18px;
            background: var(--green);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 150ms ease, transform 150ms ease;
        }

        .back-btn:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
        }

        .back-container {
            text-align: center;
        }
    </style>
</head>
<body>
<div class="page">
    <h1>Para saan ka magre-register</h1>
    <p class="subtitle">Piliin ang iyong path para magawa namin ang onboarding experience.</p>

    <div class="back-container">
    <a href="login.php" class="back-btn">← Balik sa Login</a>
    </div>

    <div class="card-grid">
        <a class="card" href="register.php?role=Buyer">
            <span class="pill">Buyer</span>
            <h2>Kumuha ng sariwang ani.</h2>
            <p>Gumawa ng account upang makipag-ugnayan sa mga beripikadong magsasaka, maglagay ng mga order, at madaling subaybayan ang mga paghahatid.</p>
        </a>
        <a class="card" href="register.php?role=Farmer">
            <span class="pill">Farmer</span>
            <h2>Ipakita ang iyong ari-arian</h2>
            <p>Magbahagi ng iyong mga produkto, mag-upload ng litrato ng ari-arian, at makakuha ng pagtutugunan sa mga aktibong mga bumibili at cooperative pools.</p>
        </a>
    </div>
</div>
</body>
</html>
