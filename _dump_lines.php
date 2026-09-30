<?php
$files = [
    ['path' => 'classes/Complaint.php', 'start' => 85, 'end' => 120],
    ['path' => 'submit_complaint.php', 'start' => 145, 'end' => 170],
    ['path' => 'view_complaint.php', 'start' => 190, 'end' => 245],
];

foreach ($files as $file) {
    echo "===== {$file['path']} {$file['start']}-{$file['end']} =====\n";
    $lines = file(__DIR__ . '/' . $file['path']);
    $end = min($file['end'], count($lines));
    for ($i = $file['start'] - 1; $i < $end; $i++) {
        echo str_pad((string)($i + 1), 4, ' ', STR_PAD_LEFT) . '|' . $lines[$i];
    }
    echo "\n";
}
