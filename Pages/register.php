<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

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

/* ---------- Helper: password policy checks ---------- */
function password_policy_checks(string $pw): array {
    return [
        'length' => (strlen($pw) >= 8),
        'lower'  => (bool)preg_match('/[a-z]/', $pw),
        'upper'  => (bool)preg_match('/[A-Z]/', $pw),
        'digit'  => (bool)preg_match('/[0-9]/', $pw),
        'special'=> (bool)preg_match('/[^a-zA-Z0-9]/', $pw),
    ];
}

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
        // Collect phone fields
        $phone_country_code = trim($_POST['phone_country_code'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $phone = trim($phone_country_code . ' ' . $phone_number);
        $country = trim($_POST['country'] ?? '');
        $language = trim($_POST['language'] ?? 'en');
        $currency = strtoupper(trim($_POST['currency'] ?? 'GBP'));
        $role = in_array($_POST['role'] ?? 'buyer', ['buyer','seller','both']) ? $_POST['role'] : 'buyer';
        $subscribe_updates = isset($_POST['subscribe_updates']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validation (existing)
        if (!in_array($title, $titles)) $errors[] = 'Please select a valid title.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid primary email.';
        if ($alt_email && !filter_var($alt_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alternate email is invalid.';
        // Check that alternate email is not the same as primary (case-insensitive)
        if ($alt_email && strcasecmp($email, $alt_email) === 0) {
            $errors[] = 'Alternate email cannot be the same as primary email.';
        }
        if ($dob && !date_create_from_format('Y-m-d', $dob)) $errors[] = 'Date of birth format invalid.';
        // Phone validation
        if ($phone_country_code || $phone_number) {
            if (!preg_match('/^\+\d{1,4}$/', $phone_country_code) || !preg_match('/^\d{5,15}$/', $phone_number)) {
                $errors[] = 'Please enter a valid phone number with country code.';
            }
        }
        if (!array_key_exists($language, $languages)) $errors[] = 'Select a valid language.';
        if (!array_key_exists($country, $countries)) $errors[] = 'Select a valid country.';
        if (!array_key_exists($currency, $currencies)) $errors[] = 'Select a valid currency.';

        if ($display_name === '') {
            $display_name = trim($first_name . ' ' . $last_name) ?: $email;
        }

        // Password policy checks (server-side authoritative)
        $pw_checks = password_policy_checks($password);
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            // require all rules pass
            foreach ($pw_checks as $rule => $ok) {
                if (!$ok) {
                    $errors[] = 'Password does not meet the required complexity (see checklist).';
                    break;
                }
            }
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
                    $uid = $pdo->lastInsertId();
                    if (function_exists('log_event')) {
                        log_event('info', 'user.register', ['user_id'=>$uid, 'email'=>$email, 'display_name'=>$display_name]);
                    }
                    $success = true;
                    $demoLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                        . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/confirm_email.php?token=' . $token;
                } catch (PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                    if (function_exists('log_event')) {
                        log_event('error','user.register.db_error',['error'=>$e->getMessage(),'email'=>$email]);
                    }
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
  <style>
    .small-note{font-size:.9rem;color:#666}
    .pw-check { list-style: none; padding-left: 0; }
    .pw-check li { margin-bottom: .25rem; }
  </style>
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
        <div class="input-group">
          <input name="phone_country_code" class="form-control" style="max-width: 80px;" maxlength="4" placeholder="+44" value="<?= h($_POST['phone_country_code'] ?? '') ?>">
          <input name="phone_number" class="form-control" maxlength="15" pattern="\d{5,15}" placeholder="1234567890" value="<?= h($_POST['phone_number'] ?? '') ?>">
        </div>
        <div class="small-note">Include country code (e.g., +44 1234567890)</div>
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
        <input id="password" name="password" type="password" class="form-control" required aria-describedby="pwHelp">
        <div id="pwHelp" class="small-note">Must be at least 8 characters and include upper, lower, a number and a special character.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirm password</label>
        <input id="password_confirm" name="password_confirm" type="password" class="form-control" required>
      </div>

      <!-- Password checklist (visible, now two columns) -->
      <div class="col-12 mt-2">
        <div class="row" id="pwChecklist" aria-live="polite">
          <div class="col-12 col-md-6">
            <ul class="pw-check mb-0">
              <li id="pw_len" class="text-danger">❌ At least 8 characters</li>
              <li id="pw_lower" class="text-danger">❌ At least one lowercase letter</li>
              <li id="pw_upper" class="text-danger">❌ At least one uppercase letter</li>
            </ul>
          </div>
          <div class="col-12 col-md-6">
            <ul class="pw-check mb-0">
              <li id="pw_digit" class="text-danger">❌ At least one number</li>
              <li id="pw_special" class="text-danger">❌ At least one special character (e.g., !@#$%)</li>
              <li id="pw_match" class="text-danger">❌ Passwords must match</li>
            </ul>
          </div>
        </div>
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

<script>
(function(){
  const pw = document.getElementById('password');
  const pwConfirm = document.getElementById('password_confirm');
  const checklist = {
    len: document.getElementById('pw_len'),
    lower: document.getElementById('pw_lower'),
    upper: document.getElementById('pw_upper'),
    digit: document.getElementById('pw_digit'),
    special: document.getElementById('pw_special'),
    match: document.getElementById('pw_match')
  };

  function testPassword(value){
    const checks = {
      len: value.length >= 8,
      lower: /[a-z]/.test(value),
      upper: /[A-Z]/.test(value),
      digit: /[0-9]/.test(value),
      special: /[^A-Za-z0-9]/.test(value)
    };
    return checks;
  }

  function updateChecklist(){
    const val = pw.value || '';
    const confirmVal = pwConfirm.value || '';
    const c = testPassword(val);
    checklist.len.className = c.len ? 'text-success' : 'text-danger';
    checklist.len.textContent = (c.len ? '✅ ' : '❌ ') + 'At least 8 characters';
    checklist.lower.className = c.lower ? 'text-success' : 'text-danger';
    checklist.lower.textContent = (c.lower ? '✅ ' : '❌ ') + 'At least one lowercase letter';
    checklist.upper.className = c.upper ? 'text-success' : 'text-danger';
    checklist.upper.textContent = (c.upper ? '✅ ' : '❌ ') + 'At least one uppercase letter';
    checklist.digit.className = c.digit ? 'text-success' : 'text-danger';
    checklist.digit.textContent = (c.digit ? '✅ ' : '❌ ') + 'At least one number';
    checklist.special.className = c.special ? 'text-success' : 'text-danger';
    checklist.special.textContent = (c.special ? '✅ ' : '❌ ') + 'At least one special character (e.g., !@#$%)';
    // Password match check
    let matchOk = false;
    if (confirmVal.length === 0 && val.length === 0) {
      checklist.match.className = 'text-danger';
      checklist.match.textContent = '❌ Passwords must match';
    } else if (confirmVal.length === 0) {
      checklist.match.className = 'text-danger';
      checklist.match.textContent = '❌ Please confirm your password';
    } else if (val === confirmVal) {
      checklist.match.className = 'text-success';
      checklist.match.textContent = '✅ Passwords match';
      matchOk = true;
    } else {
      checklist.match.className = 'text-danger';
      checklist.match.textContent = '❌ Passwords do not match';
    }
  }

  pw.addEventListener('input', updateChecklist);
  pwConfirm.addEventListener('input', updateChecklist);
  // run on load (useful if browser autofills)
  updateChecklist();
})();
</script>

</body>
</html>