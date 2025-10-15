<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$errors = [];
$success = false;
$demoLink = null;

/* ---------- Dropdown Lists ---------- */
$titles = ['Mr', 'Ms', 'Mrs', 'Dr', 'Prof', 'Mx', 'Other'];
$languages = [
  'en' => 'English',
  'fr' => 'French',
  'es' => 'Spanish',
  'de' => 'German',
  'it' => 'Italian',
  'zh' => 'Chinese',
  'hi' => 'Hindi',
  'ar' => 'Arabic',
  'ja' => 'Japanese',
  'pt' => 'Portuguese',
  'ru' => 'Russian',
  'other' => 'Other'
];
$countries = [
  'GB' => 'United Kingdom',
  'US' => 'United States',
  'IN' => 'India',
  'CA' => 'Canada',
  'AU' => 'Australia',
  'FR' => 'France',
  'DE' => 'Germany',
  'IT' => 'Italy',
  'ES' => 'Spain',
  'CN' => 'China',
  'JP' => 'Japan',
  'BR' => 'Brazil',
  'ZA' => 'South Africa',
  'SG' => 'Singapore',
  'AE' => 'United Arab Emirates',
  'OTHER' => 'Other'
];
$currencies = [
  'GBP' => 'British Pound (GBP)',
  'USD' => 'US Dollar (USD)',
  'EUR' => 'Euro (EUR)',
  'INR' => 'Indian Rupee (INR)',
  'JPY' => 'Japanese Yen (JPY)',
  'AUD' => 'Australian Dollar (AUD)',
  'CAD' => 'Canadian Dollar (CAD)',
  'CNY' => 'Chinese Yuan (CNY)',
  'AED' => 'UAE Dirham (AED)',
  'CHF' => 'Swiss Franc (CHF)',
  'ZAR' => 'South African Rand (ZAR)',
  'OTHER' => 'Other'
];

/* ---------- Registration Logic ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission (CSRF).';
    } else {
        // Collect & sanitize
        $title = trim($_POST['title'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $display_name = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $alt_email = trim($_POST['alt_email'] ?? '');
        $dob = trim($_POST['date_of_birth'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $language = trim($_POST['language'] ?? 'en');
        $currency = strtoupper(trim($_POST['currency'] ?? 'GBP'));
        $role = in_array($_POST['role'] ?? 'buyer', ['buyer','seller','both']) ? $_POST['role'] : 'buyer';
        $subscribe_updates = isset($_POST['subscribe_updates']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validation
        if (!in_array($title, $titles)) $errors[] = 'Please select a valid title.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid primary email.';
        if ($alt_email && !filter_var($alt_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alternate email is invalid.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $password_confirm) $errors[] = 'Passwords do not match.';
        if ($dob && !date_create_from_format('Y-m-d', $dob)) $errors[] = 'Date of birth format invalid.';
        if (!array_key_exists($language, $languages)) $errors[] = 'Select a valid language.';
        if (!array_key_exists($country, $countries)) $errors[] = 'Select a valid country.';
        if (!array_key_exists($currency, $currencies)) $errors[] = 'Select a valid currency.';

        if ($display_name === '') {
            $display_name = trim($first_name . ' ' . $last_name) ?: $email;
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already registered.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $token = bin2hex(random_bytes(32));

                $sql = "INSERT INTO users
                (email, alt_email, password_hash, title, first_name, last_name, display_name, date_of_birth, phone, country, language, currency, role, subscribe_updates, email_confirmation_token, is_email_confirmed, created_at)
                VALUES
                (:email, :alt_email, :password_hash, :title, :first_name, :last_name, :display_name, :date_of_birth, :phone, :country, :language, :currency, :role, :subscribe_updates, :token, 0, NOW())";

                $stmt = $pdo->prepare($sql);
                $params = [
                    'email' => $email,
                    'alt_email' => $alt_email ?: null,
                    'password_hash' => $password_hash,
                    'title' => $title,
                    'first_name' => $first_name ?: null,
                    'last_name' => $last_name ?: null,
                    'display_name' => $display_name,
                    'date_of_birth' => $dob ?: null,
                    'phone' => $phone ?: null,
                    'country' => $country,
                    'language' => $language,
                    'currency' => $currency,
                    'role' => $role,
                    'subscribe_updates' => $subscribe_updates,
                    'token' => $token
                ];

                try {
                    $stmt->execute($params);
                    $success = true;
                    $demoLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                        . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/confirm_email.php?token=' . $token;
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register — Auction Site</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>.small-note{font-size:.9rem;color:#666}</style>
</head>
<body class="p-4">
<div class="container">
  <h2>Create your account</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>'.h($e).'</li>'; ?></ul></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">Registration successful! Please confirm your email.</div>
    <?php if ($demoLink): ?>
      <div class="alert alert-info">
        <strong>Demo confirmation link:</strong>
        <a href="<?= h($demoLink) ?>"><?= h($demoLink) ?></a>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="row g-3">
      <!-- Title -->
      <div class="col-md-2">
        <label class="form-label">Title</label>
        <select name="title" class="form-select">
          <option value="">Select...</option>
          <?php foreach ($titles as $t): ?>
            <option value="<?= h($t) ?>" <?= (($_POST['title'] ?? '') === $t) ? 'selected' : '' ?>><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Names -->
      <div class="col-md-5">
        <label class="form-label">First name</label>
        <input name="first_name" class="form-control" value="<?= h($_POST['first_name'] ?? '') ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label">Last name</label>
        <input name="last_name" class="form-control" value="<?= h($_POST['last_name'] ?? '') ?>">
      </div>

      <!-- Display name -->
      <div class="col-md-6">
        <label class="form-label">Display name</label>
        <input name="display_name" class="form-control" value="<?= h($_POST['display_name'] ?? '') ?>">
      </div>

      <!-- Email fields -->
      <div class="col-md-6">
        <label class="form-label">Primary email</label>
        <input name="email" type="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Alternate email</label>
        <input name="alt_email" type="email" class="form-control" value="<?= h($_POST['alt_email'] ?? '') ?>">
      </div>

      <!-- DOB / phone / country -->
      <div class="col-md-4">
        <label class="form-label">Date of birth</label>
        <input name="date_of_birth" type="date" class="form-control" value="<?= h($_POST['date_of_birth'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Phone</label>
        <input name="phone" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Country</label>
        <select name="country" class="form-select">
          <option value="">Select...</option>
          <?php foreach ($countries as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= (($_POST['country'] ?? '') === $code) ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Language & Currency -->
      <div class="col-md-4">
        <label class="form-label">Language</label>
        <select name="language" class="form-select">
          <?php foreach ($languages as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= (($_POST['language'] ?? 'en') === $code) ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Currency</label>
        <select name="currency" class="form-select">
          <?php foreach ($currencies as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= (($_POST['currency'] ?? 'GBP') === $code) ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Role -->
      <div class="col-md-4">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="buyer" <?= (($_POST['role'] ?? '') === 'buyer') ? 'selected' : '' ?>>Buyer</option>
          <option value="seller" <?= (($_POST['role'] ?? '') === 'seller') ? 'selected' : '' ?>>Seller</option>
          <option value="both" <?= (($_POST['role'] ?? '') === 'both') ? 'selected' : '' ?>>Both</option>
        </select>
      </div>

      <!-- Password -->
      <div class="col-md-6">
        <label class="form-label">Password</label>
        <input name="password" type="password" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirm password</label>
        <input name="password_confirm" type="password" class="form-control" required>
      </div>

      <!-- Subscription -->
      <div class="col-md-6 mt-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="subscribe_updates" name="subscribe_updates" <?= isset($_POST['subscribe_updates']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="subscribe_updates">Subscribe to updates and notifications</label>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <button class="btn btn-primary">Register</button>
      <a href="login.php" class="btn btn-link">Already have an account?</a>
    </div>
  </form>
</div>
</body>
</html>