<?php
declare(strict_types=1);

// -----------------------
// Load .env variables
// -----------------------
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// -----------------------
// Backend classes (Logger, Database, TemplateEngine, MailgunSender, Validator, NotificationService)
// For brevity, assume classes are copied exactly from original index.php
// -----------------------

class Logger {
    private string $logFile;
    private bool $debug;

    public function __construct(string $logFile, bool $debug = false) {
        $this->logFile = $logFile;
        $this->debug = $debug;
    }

    public function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
        @error_log($logMessage, 3, $this->logFile);
        if ($this->debug || $level === 'ERROR' || $level === 'CRITICAL') {
            error_log(trim($logMessage));
        }
    }

    public function info(string $message, array $context = []): void { $this->log('INFO', $message, $context); }
    public function error(string $message, array $context = []): void { $this->log('ERROR', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->log('WARNING', $message, $context); }
    public function debug(string $message, array $context = []): void {
        if ($this->debug) { $this->log('DEBUG', $message, $context); }
    }
}

class RateLimiter {
    private array $requests = [];
    private int $limit;
    private Logger $logger;

    public function __construct(int $limit, Logger $logger) {
        $this->limit = $limit;
        $this->logger = $logger;
    }

    public function checkLimit(string $clientId): bool {
        $now = time();
        if (!isset($this->requests[$clientId])) { $this->requests[$clientId] = []; }
        $this->requests[$clientId] = array_filter(
            $this->requests[$clientId],
            fn($timestamp) => $timestamp > ($now - 60)
        );
        if (count($this->requests[$clientId]) >= $this->limit) {
            $this->logger->warning('Rate limit exceeded', ['client' => $clientId]);
            return false;
        }
        $this->requests[$clientId][] = $now;
        return true;
    }

    public function getRemaining(string $clientId): int {
        return max(0, $this->limit - count($this->requests[$clientId] ?? []));
    }
}

class TemplateEngine {
    private array $templates;
    private array $subjectTemplates;

    public function __construct(array $templates, array $subjectTemplates = []) {
        $this->templates = $templates;
        $this->subjectTemplates = $subjectTemplates;
    }

    public function render(string $name, array $variables): string {
        if (!isset($this->templates[$name])) {
            throw new Exception("Template '{$name}' not found");
        }
        return $this->renderTemplate($this->templates[$name], $variables);
    }

    public function renderSubject(string $name, array $variables): string {
        if (!isset($this->subjectTemplates[$name])) {
            return ucfirst(str_replace('_', ' ', $name));
        }
        return $this->renderTemplate($this->subjectTemplates[$name], $variables);
    }

    private function renderTemplate(string $template, array $variables): string {
        foreach ($variables as $key => $value) {
            $safeValue = is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
            $template = str_replace('{{ ' . $key . ' }}', (string)$safeValue, $template);
        }
        return $template;
    }

    public function exists(string $name): bool { return isset($this->templates[$name]); }
}

class Database {
    private ?PDO $pdo = null;
    private array $config;
    private Logger $logger;

    public function __construct(array $config, Logger $logger) {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getConnection(): PDO {
        if ($this->pdo !== null) { return $this->pdo; }
        try {
            $this->pdo = new PDO(
                $this->config['db_dsn'],
                $this->config['db_user'],
                $this->config['db_pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $this->logger->debug('Database connection established');
            return $this->pdo;
        } catch (PDOException $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('Database connection failed');
        }
    }

    public function getRecipientEmail(string $recipientId): ?string {
        try {
            $stmt = $this->getConnection()->prepare('SELECT email FROM recipient_preferences WHERE recipient_id = :rid');
            $stmt->execute([':rid' => $recipientId]);
            $row = $stmt->fetch();
            return $row['email'] ?? null;
        } catch (Exception $e) {
            $this->logger->error('Could not fetch recipient email', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

class Validator {
    public function validateSendRequest(array $data): array {
        $errors = [];
        if (!isset($data['recipient_id']) || !is_string($data['recipient_id'])) {
            $errors['recipient_id'] = 'recipient_id is required and must be a string';
        } elseif (trim($data['recipient_id']) === '') {
            $errors['recipient_id'] = 'recipient_id cannot be empty';
        } elseif (strlen($data['recipient_id']) > 255) {
            $errors['recipient_id'] = 'recipient_id cannot exceed 255 characters';
        }
        if (!isset($data['template_name']) || !is_string($data['template_name'])) {
            $errors['template_name'] = 'template_name is required and must be a string';
        } elseif (trim($data['template_name']) === '') {
            $errors['template_name'] = 'template_name cannot be empty';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['template_name'])) {
            $errors['template_name'] = 'template_name can only contain letters, numbers, hyphens, and underscores';
        }
        if (!isset($data['variable_data']) || !is_array($data['variable_data'])) {
            $errors['variable_data'] = 'variable_data is required and must be an object';
        }
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . json_encode($errors));
        }
        return [
            'recipient_id'  => trim($data['recipient_id']),
            'template_name' => trim($data['template_name']),
            'variable_data' => $data['variable_data'],
        ];
    }
}

// ============================================================================
// EMAIL SENDER (MAILGUN)
// ============================================================================

class MailgunSender {
    private string $apiKey;
    private string $domain;
    private string $region;
    private string $fromEmail;
    private string $fromName;
    private Logger $logger;

    public function __construct(array $config, Logger $logger) {
        $this->apiKey    = $config['mailgun_api_key'];
        $this->domain    = $config['mailgun_domain'];
        $this->region    = $config['mailgun_region'];
        $this->fromEmail = $config['from_email'];
        $this->fromName  = $config['from_name'];
        $this->logger    = $logger;
    }

    public function send(string $toEmail, string $subject, string $message): bool {
        if (empty($this->apiKey) || empty($this->domain)) {
            $this->logger->warning('Mailgun not configured, email not sent', ['to' => $toEmail]);
            return false;
        }
        $baseUrl = $this->region === 'eu' ? 'https://api.eu.mailgun.net/v3' : 'https://api.mailgun.net/v3';
        $url = "{$baseUrl}/{$this->domain}/messages";
        $data = [
            'from'    => "{$this->fromName} <{$this->fromEmail}>",
            'to'      => $toEmail,
            'subject' => $subject,
            'text'    => $message,
        ];
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
                CURLOPT_USERPWD        => "api:{$this->apiKey}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $data,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($httpCode === 200) {
                $this->logger->info('Email sent via Mailgun', ['to' => $toEmail]);
                return true;
            }
            $this->logger->error('Mailgun error', ['http_code' => $httpCode, 'response' => $response, 'curl_error' => $curlError]);
            return false;
        } catch (Exception $e) {
            $this->logger->error('Mailgun exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function isConfigured(): bool { return !empty($this->apiKey) && !empty($this->domain); }
}
// -----------------------
// Config
// -----------------------
function requireEnv(string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') throw new RuntimeException("Missing environment variable: {$key}");
    return $value;
}

function optionalEnv(string $key, string $default): string {
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

$config = [
    'db_dsn'          => requireEnv('DB_DSN'),
    'db_user'         => requireEnv('DB_USER'),
    'db_pass'         => requireEnv('DB_PASS'),
    'api_key'         => requireEnv('API_KEY'),
    'rate_limit'      => (int) optionalEnv('RATE_LIMIT', '60'),
    'debug'           => filter_var(optionalEnv('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'log_file'        => optionalEnv('LOG_PATH', __DIR__ . '/notifications.log'),
    'mailgun_api_key' => optionalEnv('MAILGUN_API_KEY', ''),
    'mailgun_domain'  => optionalEnv('MAILGUN_DOMAIN', ''),
    'mailgun_region'  => optionalEnv('MAILGUN_REGION', 'us'),
    'from_email'      => optionalEnv('FROM_EMAIL', 'noreply@test.com'),
    'from_name'       => optionalEnv('FROM_NAME', 'Notification Service'),
    'templates' => [
        'appointment' => 'This is a reminder that you have a Health Matters appointment at 11am today with Dr Jones at the Preston Clinic.',
        'medication'  => 'Time to take your Vitamin D supplement.',
        'habit'       => "You've been inactive for a while. A short walk could boost your energy.",
        'goal'        => "You completed your daily exercise by doing a 5k run and beat your PB, keep it up!",
        'referral'    => 'Your referral form has been submitted successfully.',
        'missed'      => 'You missed your daily activity yesterday, try doing some exercise.',
        'welcome'     => 'Welcome! Your Health Matters account is ready.',
    ],
];

class NotificationService {
    private Database $db;
    private TemplateEngine $templates;
    private MailgunSender $emailSender;
    private Logger $logger;

    public function __construct(Database $db, TemplateEngine $templates, MailgunSender $emailSender, Logger $logger) {
        $this->db          = $db;
        $this->templates   = $templates;
        $this->emailSender = $emailSender;
        $this->logger      = $logger;
    }

    public function send(string $recipientId, string $templateName, array $variables): array {
        $this->logger->info('Processing notification', ['recipient_id' => $recipientId, 'template' => $templateName]);
        $channel = $this->getPreferredChannel($recipientId);
        if (!$this->templates->exists($templateName)) {
            throw new Exception("Template '{$templateName}' not found");
        }
        $message        = $this->templates->render($templateName, $variables);
        $subject        = $this->templates->renderSubject($templateName, $variables);
        $notificationId = $this->saveNotification($recipientId, $templateName, $channel, $message);
        $actuallyTriedToSend = $this->emailSender->isConfigured();
        $sendResult = false;
        if ($actuallyTriedToSend) {
            $sendResult = $this->dispatchNotification($recipientId, $channel, $subject, $message);
            if ($sendResult) { $this->markNotificationSent($notificationId); }
            else { $this->markNotificationFailed($notificationId); }
        }
        $nowIso    = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
        $apiStatus = !$actuallyTriedToSend ? 'queued' : ($sendResult ? 'sent' : 'failed');
        return [
            'notification_id' => $notificationId,
            'event_type'      => $templateName,
            'channel'         => $channel,
            'user_id'         => $recipientId,
            'status'          => $apiStatus,
            'sent_at'         => ($apiStatus === 'sent') ? $nowIso : null,
            'created_at'      => $nowIso,
        ];
    }

    private function getPreferredChannel(string $recipientId): string {
        try {
            $stmt = $this->db->getConnection()->prepare('SELECT preferred_channel FROM recipient_preferences WHERE recipient_id = :rid');
            $stmt->execute([':rid' => $recipientId]);
            $row = $stmt->fetch();
            return $row['preferred_channel'] ?? 'Email';
        } catch (Exception $e) {
            $this->logger->warning('Could not fetch preference, using default', ['error' => $e->getMessage()]);
            return 'Email';
        }
    }

    private function saveNotification(string $recipientId, string $templateName, string $channel, string $message): string {
        $notificationId = $this->generateUuidV4();
        $stmt = $this->db->getConnection()->prepare(
            'INSERT INTO notifications (notification_id, event_type, channel, user_id, status, message, sent_at)
             VALUES (:nid, :event_type, :channel, :user_id, :status, :message, :sent_at)'
        );
        $stmt->execute([
            ':nid'        => $notificationId,
            ':event_type' => $templateName,
            ':channel'    => $channel,
            ':user_id'    => $recipientId,
            ':status'     => 'queued',
            ':message'    => $message,
            ':sent_at'    => null,
        ]);
        return $notificationId;
    }

    private function dispatchNotification(string $recipientId, string $channel, string $subject, string $message): bool {
        if ($channel === 'Email') { return $this->sendEmail($recipientId, $subject, $message); }
        elseif ($channel === 'SMS') { return $this->sendSMS($recipientId, $message); }
        elseif ($channel === 'Push') { return $this->sendPush($recipientId, $message); }
        $this->logger->warning('Unknown channel type', ['channel' => $channel]);
        return false;
    }

    private function sendEmail(string $recipientId, string $subject, string $message): bool {
        $toEmail = $this->db->getRecipientEmail($recipientId);
        if (!$toEmail) {
            $this->logger->warning('No email address found for recipient', ['recipient_id' => $recipientId]);
            return false;
        }
        return $this->emailSender->send($toEmail, $subject, $message);
    }

    private function sendSMS(string $recipientId, string $message): bool {
        $this->logger->info('SMS SIMULATED (not sent)', ['recipient_id' => $recipientId, 'message_preview' => substr($message, 0, 100)]);
        return true;
    }

    private function sendPush(string $recipientId, string $message): bool {
        $this->logger->info('PUSH SIMULATED (not sent)', ['recipient_id' => $recipientId, 'message_preview' => substr($message, 0, 100)]);
        return true;
    }

    private function generateUuidV4(): string {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20,12));
    }

    private function markNotificationSent(string $notificationId): void {
        $stmt = $this->db->getConnection()->prepare('UPDATE notifications SET status = :status, sent_at = NOW() WHERE notification_id = :nid');
        $stmt->execute([':status' => 'sent', ':nid' => $notificationId]);
    }

    private function markNotificationFailed(string $notificationId): void {
        $stmt = $this->db->getConnection()->prepare('UPDATE notifications SET status = :status WHERE notification_id = :nid');
        $stmt->execute([':status' => 'failed', ':nid' => $notificationId]);
    }
}

// ============================================================================
// HTTP HANDLING
// ============================================================================

function respondJson(int $status, array $data): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function authenticate(string $apiKey, Logger $logger): void {
    $provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($provided)) {
        $logger->warning('Missing API key', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        respondJson(401, ['error' => 'API key is required']);
    }
    if (!hash_equals($apiKey, $provided)) {
        $logger->warning('Invalid API key', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        respondJson(401, ['error' => 'Invalid API key']);
    }
}

function getClientId(): string { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }


// -----------------------
// Initialize services
// -----------------------
$logger      = new Logger($config['log_file'], $config['debug']);
$db          = new Database($config, $logger);
$templates   = new TemplateEngine($config['templates']);
$emailSender = new MailgunSender($config, $logger);
$validator   = new Validator();
$service     = new NotificationService($db, $templates, $emailSender, $logger);


// -----------------------
// Fetch notification history from DB
// -----------------------
try {
    $stmt = $db->getConnection()->query('SELECT * FROM notifications ORDER BY created_at DESC');
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logger->error('Could not fetch notifications', ['error' => $e->getMessage()]);
    $notifications = [];
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

<!-- Notification History -->
<h3>Notification History</h3>
<?php if (!empty($notifications)): ?>
    <ul class="history">
        <?php foreach ($notifications as $n): ?>
            <li>
                <strong><?= ucfirst($n['event_type']) ?>:</strong>
                <?= htmlspecialchars($n['message']) ?>
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