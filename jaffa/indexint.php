<?php
declare(strict_types=1);
session_start();

// -------------------- Load .env --------------------
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv("$key=$value");
        $_ENV[$key] = trim($value);
        $_SERVER[$key] = trim($value);
    }
}

// -------------------- DB Connection --------------------
try {
    $pdo = new PDO(
        getenv('DB_DSN'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("❌ Error connecting to database: " . $e->getMessage());
}

// -------------------- Notification Templates --------------------
$templates = [
    "appointment" => [
        "title" => "🏥 Appointment Reminder",
        "message" => "This is a reminder that you have a Health Matters appointment at 11am today with Dr Jones at the Preston Clinic."
    ],
    "medication" => [
        "title" => "💊 Medication Reminder",
        "message" => "Time to take your Vitamin D supplement."
    ],
    "habit" => [
        "title" => "🚶 Healthy Habit Reminder",
        "message" => "You've been inactive for a while. A short walk could boost your energy."
    ],
    "goal" => [
        "title" => "🎉 Goal Achieved!",
        "message" => "You completed your daily exercise by doing a 5k run and beat your PB, keep it up!"
    ],
    "referral" => [
        "title" => "✅ Referral Submitted",
        "message" => "Your referral form has been submitted successfully."
    ],
    "missed" => [
        "title" => "⚠️ Missed Activity",
        "message" => "You missed your daily activity yesterday, try doing some exercise."
    ],
];

// -------------------- Handle Notification Creation --------------------
$type = $_GET['type'] ?? "";

if ($type && isset($templates[$type])) {
    $title = $templates[$type]['title'];
    $message = $templates[$type]['message'];

    // Save to database
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (notification_id, event_type, channel, user_id, status, message, sent_at)
             VALUES (:nid, :event_type, :channel, :user_id, :status, :message, :sent_at)'
        );
        $nid = bin2hex(random_bytes(16));
        $stmt->execute([
            ':nid' => $nid,
            ':event_type' => $type,
            ':channel' => 'Email',
            ':user_id' => '11111111-1111-1111-1111-111111111111',
            ':status' => 'queued',
            ':message' => $message,
            ':sent_at' => null,
        ]);
    } catch (Exception $e) {
        die("❌ Error saving notification: " . $e->getMessage());
    }
}

// -------------------- Fetch Current Notification History from DB --------------------
try {
    $stmt = $pdo->query('SELECT notification_id, event_type, message FROM notifications ORDER BY created_at DESC');
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $_SESSION['notifications'] = $notifications;
} catch (Exception $e) {
    $notifications = $_SESSION['notifications'] ?? [];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Health Matters Notification System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Health Matters Notification Demo</h1>
<p>Click a button to simulate a system notification.</p>

<div class="buttons">

<a href="notifications.php?type=appointment">🏥 Appointment</a>

<a href="notifications.php?type=medication">💊 Medication</a>

<a href="notifications.php?type=habit">🚶 Habit</a>

<a href="notifications.php?type=goal">🎉 Goal</a>

<a href="notifications.php?type=referral">✅ Referral</a>

<a href="notifications.php?type=missed">⚠️ Missed</a>

<a href="notifications.php?type=stack">Stack Demo</a>

<a href="notifications.php?type=history">Notification History</a>

</div>

<?php if ($type && isset($templates[$type])): ?>
<div class="notification slide-in">
    <h2><?php echo $templates[$type]['title']; ?></h2>
    <p><?php echo $templates[$type]['message']; ?></p>
    <button class="dismiss-btn">Dismiss</button>
</div>
<?php endif; ?>

<br>
<a href="indexint.php">← Back</a>

<h3>Notification History</h3>
<?php if (!empty($notifications)): ?>
<ul class="history">
    <?php foreach ($notifications as $n): ?>
        <li>
            <strong><?php echo htmlspecialchars($n['event_type']); ?>:</strong>
            <span><?php echo htmlspecialchars($n['message']); ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<?php else: ?>
    <p>No notifications yet.</p>
<?php endif; ?>

<script>
const notif = document.querySelector('.notification');
const dismissBtn = document.querySelector('.dismiss-btn');
if (notif && dismissBtn) {
    setTimeout(() => { notif.style.display = 'none'; }, 5000);
    dismissBtn.addEventListener('click', () => { notif.style.display = 'none'; });
}
</script>

</body>
</html>