<?php
// Bootstrap to use Eloquent directly
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$user = User::where('email', 'parthcnc45@gmail.com')->first();
if (!$user) {
    die("User parthcnc45@gmail.com not found!");
}

$ccList = [
    'nagender@codeandcore.com',
    'saurabhsoni.cnc@gmail.com',
    'nikul@codeandcore.com'
];

echo "==== Original CC List ====\n";
print_r($ccList);

// Scenario A
$ccListA = $ccList;
$manager = $user->reportingManager;
$managerEmail = $manager ? $manager->email : null;

if ($manager && !empty($managerEmail)) {
    $ccListA[] = $managerEmail;
}
$ccListA = array_unique($ccListA);
echo "\n--- Scenario 1: Actual User in Database ---\n";
echo "User Database Reporting Manager: " . ($managerEmail ?? 'NOT ASSIGNED') . "\n";
echo "Final CC Emails:\n";
print_r(array_values($ccListA));

// Scenario B
$ccListB = $ccList;
$mockManagerEmail = 'superboss@codeandcore.com';
if ($mockManagerEmail) {
    $ccListB[] = $mockManagerEmail;
}
$ccListB = array_unique($ccListB);
echo "\n--- Scenario 2: Manager has a UNIQUE email ---\n";
echo "Mock Manager Email: " . $mockManagerEmail . "\n";
echo "Final CC Emails (Should be 4 emails):\n";
print_r(array_values($ccListB));

// Scenario C
$ccListC = $ccList;
$mockManagerEmailDuplicate = 'nikul@codeandcore.com';
if ($mockManagerEmailDuplicate) {
    $ccListC[] = $mockManagerEmailDuplicate;
}
$ccListC = array_unique($ccListC);
echo "\n--- Scenario 3: Manager has DUPLICATE email ---\n";
echo "Mock Manager Email: " . $mockManagerEmailDuplicate . " (Already exists in default CC)\n";
echo "Final CC Emails (Should Still be 3 emails, duplicate removed!):\n";
print_r(array_values($ccListC));
