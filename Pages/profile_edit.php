<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/header.php';

ini_set('display_errors',1);
error_reporting(E_ALL);

login_required();

$user_id = current_user_id();

// lists: keep these in sync with register.php
$titles = ['Mr', 'Ms', 'Mrs', 'Dr', 'Prof', 'Mx', 'Other'];
$languages = [
  'en' => 'English','fr'=>'French','es'=>'Spanish','de'=>'German','it'=>'Italian',
  'zh'=>'Chinese','hi'=>'Hindi','ar'=>'Arabic','ja'=>'Japanese','pt'=>'Portuguese','ru'=>'Russian','other'=>'Other'
];
$countries = [
  'GB'=>'United Kingdom','US'=>'United States','IN'=>'India','CA'=>'Canada','AU'=>'Australia','FR'=>'France','DE'=>'Germany','IT'=>'Italy','ES'=>'Spain','CN'=>'China','JP'=>'Japan','BR'=>'Brazil','ZA'=>'South Africa','SG'=>'Singapore','AE'=>'United Arab Emirates','OTHER'=>'Other'
];
$currencies = [
  'GBP'=>'British Pound (GBP)','USD'=>'US Dollar (USD)','EUR'=>'Euro (EUR)','INR'=>'Indian Rupee (INR)','JPY'=>'Japanese Yen (JPY)','AUD'=>'Australian Dollar (AUD)','CAD'=>'Canadian Dollar (CAD)','CNY'=>'Chinese Yuan (CNY)','AED'=>'UAE Dirham (AED)','CHF'=>'Swiss Franc (CHF)','ZAR'=>'South African Rand (ZAR)','OTHER'=>'Other'
];
$roles = ['buyer','seller','both'];

// fetch current record
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id LIMIT 1");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: logout.php');
    exit;
}

$errors = [];
$success = false;

/*
 * Change Password handler
 * Detect via hidden form field: form_type = "change_password"
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'change_password') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission (CSRF).';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        // fetch current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :id LIMIT 1");
        $stmt->execute(['id' => $user_id]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
            if ($new !== $confirm) $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id");
            $stmt->execute(['hash' => $hash, 'id' => $user_id]);
            $_SESSION['flash'] = 'Password changed successfully.';
            header('Location: profile.php');
            exit;
        }
    }
}

/*
 * Profile update handler (existing)
 * Detect via form_type absent or set to "profile_update"
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['form_type'] ?? '') !== 'change_password')) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission (CSRF).';
    } else {
        // collect inputs
        $title = trim($_POST['title'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $display_name = trim($_POST['display_name'] ?? '');
        $alt_email = trim($_POST['alt_email'] ?? '');
        $dob = trim($_POST['date_of_birth'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $language = trim($_POST['language'] ?? 'en');
        $currency = strtoupper(trim($_POST['currency'] ?? 'GBP'));
        $role = trim($_POST['role'] ?? $user['role']);
        $subscribe_updates = isset($_POST['subscribe_updates']) ? 1 : 0;

        // validation
        if (!in_array($title, $titles)) $errors[] = 'Please select a valid title.';
        if ($alt_email && !filter_var($alt_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Alternate email invalid.';
        if ($dob && !date_create_from_format('Y-m-d', $dob)) $errors[] = 'Date of birth format invalid.';
        if (!array_key_exists($language, $languages)) $errors[] = 'Language invalid.';
        if (!array_key_exists($country, $countries)) $errors[] = 'Country invalid.';
        if (!array_key_exists($currency, $currencies)) $errors[] = 'Currency invalid.';
        if (!in_array($role, $roles)) $errors[] = 'Role invalid.';

        if ($display_name === '') {
            $display_name = trim($first_name . ' ' . $last_name) ?: $user['email'];
        }

        if (empty($errors)) {
            $sql = "UPDATE users SET title = :title, first_name = :first_name, last_name = :last_name, display_name = :display_name, alt_email = :alt_email, date_of_birth = :dob, phone = :phone, country = :country, language = :language, currency = :currency, role = :role, subscribe_updates = :subscribe WHERE user_id = :id";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    'title'=>$title ?: null,
                    'first_name'=>$first_name ?: null,
                    'last_name'=>$last_name ?: null,
                    'display_name'=>$display_name ?: null,
                    'alt_email'=>$alt_email ?: null,
                    'dob'=>$dob ?: null,
                    'phone'=>$phone ?: null,
                    'country'=>$country ?: null,
                    'language'=>$language,
                    'currency'=>$currency,
                    'role'=>$role,
                    'subscribe'=>$subscribe_updates,
                    'id'=>$user_id
                ]);
                $_SESSION['role'] = $role;
                // refresh $user variable so the form shows updated values without re-login
                $stmt2 = $pdo->prepare("SELECT * FROM users WHERE user_id = :id LIMIT 1");
                $stmt2->execute(['id' => $user_id]);
                $user = $stmt2->fetch();

                $_SESSION['flash'] = 'Profile updated successfully.';
                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// CSRF token
$csrf = csrf_token();
?>

<div class="container">
  <h2>Edit profile</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>' . h($e) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="form_type" value="profile_update">

    <div class="row g-3">
      <div class="col-md-2">
        <label class="form-label">Title</label>
        <select name="title" class="form-select">
          <option value="">Select...</option>
          <?php foreach ($titles as $t): ?>
            <option value="<?= h($t) ?>" <?= ($user['title'] === $t) ? 'selected' : '' ?>><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label">First name</label>
        <input name="first_name" class="form-control" value="<?= h($user['first_name'] ?? '') ?>">
      </div>

      <div class="col-md-5">
        <label class="form-label">Last name</label>
        <input name="last_name" class="form-control" value="<?= h($user['last_name'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Display name</label>
        <input name="display_name" class="form-control" value="<?= h($user['display_name'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Alternate email</label>
        <input name="alt_email" type="email" class="form-control" value="<?= h($user['alt_email'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Date of birth</label>
        <input name="date_of_birth" type="date" class="form-control" value="<?= h($user['date_of_birth'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Phone</label>
        <input name="phone" class="form-control" value="<?= h($user['phone'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Country</label>
        <select name="country" class="form-select">
          <option value="">Select...</option>
          <?php foreach ($countries as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= (($user['country'] ?? '') === $code) ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Language</label>
        <select name="language" class="form-select">
          <?php foreach ($languages as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= (($user['language'] ?? '') === $code) ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Currency</label>
        <select name="currency" class="form-select">
          <?php foreach ($currencies as $code => $name): ?>
            <option value="<?= h($code) ?>" <?= (($user['currency'] ?? '') === $code) ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <?php foreach ($roles as $r): ?>
            <option value="<?= h($r) ?>" <?= (($user['role'] ?? '') === $r) ? 'selected' : '' ?>><?= h(ucfirst($r)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6 mt-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="subscribe_updates" name="subscribe_updates" <?= ($user['subscribe_updates']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="subscribe_updates">Subscribe to updates</label>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <button class="btn btn-primary">Save changes</button>
      <a href="profile.php" class="btn btn-link">Cancel</a>
    </div>
  </form>

  <!-- Change Password Form -->
  <hr class="my-4">
  <h3>Change password</h3>
  <form method="post" class="mb-4" novalidate>
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="form_type" value="change_password">

    <div class="mb-3">
      <label class="form-label">Current password</label>
      <input name="current_password" type="password" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">New password</label>
      <input name="new_password" type="password" class="form-control" required>
      <div class="small-note">At least 8 characters</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Confirm new password</label>
      <input name="new_password_confirm" type="password" class="form-control" required>
    </div>

    <button class="btn btn-warning">Change password</button>
  </form>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';