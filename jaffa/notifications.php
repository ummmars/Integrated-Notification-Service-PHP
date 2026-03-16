<?php
session_start();

/* -------------------------------
LOAD BACKEND SYSTEM
--------------------------------*/
require_once "indexint1.php"; // gives access to NotificationService

/* ---------- FORCE USER LOGIN ---------- */
if(!isset($_SESSION['user'])){
    header("Location: setuser.php");
    exit();
}

$user = $_SESSION['user'];
$type = $_GET['type'] ?? "";

/* ---------- DELETE HISTORY ---------- */
if(isset($_POST['deleteHistory'])){
    // Clear session notifications
    $_SESSION['notifications'] = [];

    // Clear notifications from database
    try {
        $stmt = $db->getConnection()->prepare('DELETE FROM notifications');
        $stmt->execute();
    } catch (Exception $e) {
        $logger->error('Failed to delete notifications', ['error' => $e->getMessage()]);
    }
}

/* ---------- LOAD HISTORY ---------- */
$notifications = $_SESSION['notifications'] ?? [];
$displayNotifications = [];

/* ---------- HELPER FUNCTIONS ---------- */

function addToHistory($title,$message,&$notifications){
    $time = date("H:i:s");

    foreach($notifications as $n){
        if($n['title'] === $title && $n['message'] === $message) return;
    }

    $notifications[] = ['title'=>$title,'message'=>$message,'time'=>$time];

    if(count($notifications) > 10){
        array_shift($notifications);
    }
}

function showSingle($title,$message,&$displayNotifications,&$notifications){
    $displayNotifications[] = ['title'=>$title,'message'=>$message];
    addToHistory($title,$message,$notifications);
}

/* ---------- STACK POOL ---------- */
$stackPool = [
["title"=>"💊 Medication Reminder","message"=>"$user, take your medication."],
["title"=>"🚶 Healthy Habit Reminder","message"=>"$user, try doing some exercise."],
["title"=>"🎉 Goal Achieved","message"=>"$user reached 10k steps!"],
["title"=>"🏥 Appointment Reminder","message"=>"$user has an appointment at 11am."],
["title"=>"⚠️ Missed Activity","message"=>"$user missed yesterday's activity."],
["title"=>"✅ Referral Submitted","message"=>"Referral sent successfully."],
["title"=>"👋 Welcome","message"=>"Welcome $user!"]
];

/* ---------- SWITCH ---------- */
switch($type){

case "appointment":
showSingle(
"🏥 Appointment Reminder",
"$user, appointment at 11am with Dr Jones.",
$displayNotifications,
$notifications
);
$service->send("11111111-1111-1111-1111-111111111111","appointment",[]);
break;

case "medication":
showSingle(
"💊 Medication Reminder",
"$user, time to take medication.",
$displayNotifications,
$notifications
);
$service->send("11111111-1111-1111-1111-111111111111","medication",[]);
break;

case "habit":
showSingle(
"🚶 Healthy Habit Reminder",
"$user, you have been inactive.",
$displayNotifications,
$notifications
);
$service->send("11111111-1111-1111-1111-111111111111","habit",[]);
break;

case "goal":
showSingle(
"🎉 Goal Achieved",
"$user reached 10k steps!",
$displayNotifications,
$notifications
);
$service->send("11111111-1111-1111-1111-111111111111","goal",[]);
break;

case "referral":
showSingle(
"✅ Referral Submitted",
"Referral sent successfully.",
$displayNotifications,
$notifications
);
$service->send("11111111-1111-1111-1111-111111111111","referral",[]);
break;

case "missed":
showSingle(
"⚠️ Missed Activity",
"$user missed yesterday’s activity.",
$displayNotifications,
$notifications
);
$service->send("11111111-1111-1111-1111-111111111111","missed",[]);
break;

case "stack":

$titleToTemplate = [
    "💊 Medication Reminder"      => "medication",
    "🚶 Healthy Habit Reminder"   => "habit",
    "🎉 Goal Achieved"            => "goal",
    "🏥 Appointment Reminder"     => "appointment",
    "⚠️ Missed Activity"          => "missed",
    "✅ Referral Submitted"       => "referral",
    "👋 Welcome"                  => "welcome",
];

$keys = array_rand($stackPool, 3);

foreach ($keys as $k) {

    $displayNotifications[] = $stackPool[$k];

    addToHistory(
        $stackPool[$k]['title'],
        $stackPool[$k]['message'],
        $notifications
    );

    // Send to database using proper template
    $templateName = $titleToTemplate[$stackPool[$k]['title']] ?? "habit";
    $service->send(
        "11111111-1111-1111-1111-111111111111", // example recipient
        $templateName,
        []
    );

}

break;

case "history":
break;

}

/* SAVE HISTORY */
$_SESSION['notifications'] = $notifications;
?>

<!DOCTYPE html>
<html>
<head>
<title>Notification</title>
<link rel="stylesheet" href="style.css">
</head>

<body>

<div class="notification-container">

<?php foreach($displayNotifications as $n): ?>

<div class="notification info">

<div class="notification-content">

<div class="icon">🔔</div>

<div class="text">

<h2><?php echo $n['title']; ?></h2>

<p><?php echo $n['message']; ?></p>

<div class="progress"></div>

<button class="dismiss-btn"
onclick="this.closest('.notification').remove();">
Dismiss
</button>

</div>
</div>
</div>

<?php endforeach; ?>

</div>

<?php if($type == "history"): ?>

<h3>Notification History</h3>

<?php if(!empty($notifications)): ?>

<ul class="history">

<?php foreach(array_reverse($notifications) as $n): ?>

<li>

<strong><?php echo $n['title']; ?></strong>

(<?php echo $n['time']; ?>)

<br>

<?php echo $n['message']; ?>

</li>

<?php endforeach; ?>

</ul>

<form method="post">

<button type="submit"
name="deleteHistory"
class="delete-btn">

Delete Notification History

</button>

</form>

<?php else: ?>

<p>No notifications yet.</p>

<?php endif; ?>

<?php endif; ?>

<a href="indexint1.php" class="back-btn">← Back</a>

<script>

const notifications = document.querySelectorAll('.notification');

notifications.forEach((notif, index) => {

setTimeout(() => {

notif.style.display = 'block';

notif.classList.add('slide-in');

}, index * 1000);

setTimeout(() => {

notif.classList.remove('slide-in');

notif.classList.add('slide-out');

setTimeout(() => {

notif.remove();

}, 500);

}, 10000 + (index * 1000));

});

</script>

</body>
</html>