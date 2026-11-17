<?php
// Ensure this script only runs once per database lifecycle
require_once __DIR__ . '/../config/database.php';

// Check if fee column is already present
$check = $conn->query("SHOW COLUMNS FROM document_types LIKE 'fee'");
if ($check && $check->num_rows > 0) {
    return; // Already migrated, exit silently
}

// 1. Add columns to document_types
$columns_to_add = [
    'fee' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER description",
    'processing_time' => "VARCHAR(100) NULL DEFAULT '2-3 Business Days' AFTER fee",
    'requirements' => "TEXT NULL AFTER processing_time",
    'instructions' => "TEXT NULL AFTER requirements"
];

foreach ($columns_to_add as $col => $definition) {
    $res = $conn->query("SHOW COLUMNS FROM document_types LIKE '$col'");
    if ($res && $res->num_rows == 0) {
        $conn->query("ALTER TABLE document_types ADD COLUMN $col $definition");
    }
}

// 2. Populate sample fees and physical requirements
$samples = [
    'Transcript of Records (TOR)' => [
        'fee' => 150.00,
        'processing_time' => '3-5 Working Days',
        'requirements' => "• Valid Student ID or Government-Issued ID\n• 2x2 colored ID picture (formal attire, white background)\n• Duly accomplished Clearance Form (for graduates/transferees)",
        'instructions' => "First request must present official clearance. Subsequent requests require only valid ID."
    ],
    'Certificate of Enrollment' => [
        'fee' => 50.00,
        'processing_time' => '1-2 Working Days',
        'requirements' => "• Valid Student ID\n• Current Semester Study Load / Certificate of Registration (COR)",
        'instructions' => "Used for scholarship applications, SSS/GSIS dependent claims, or employment verification."
    ],
    'Certificate of Grades' => [
        'fee' => 75.00,
        'processing_time' => '2-3 Working Days',
        'requirements' => "• Valid Student ID\n• Copy of previous semester grades or student portal printout",
        'instructions' => "Official grades copy authenticated by the campus registrar."
    ],
    'Good Moral Certificate' => [
        'fee' => 50.00,
        'processing_time' => '1-2 Working Days',
        'requirements' => "• Valid Student ID\n• Clearance from Campus Guidance Office / Dean of Student Affairs",
        'instructions' => "Certification of good student conduct during residency."
    ],
    'Certification of Graduation' => [
        'fee' => 100.00,
        'processing_time' => '2-3 Working Days',
        'requirements' => "• Valid Government-Issued ID\n• Certified True Copy of Special Order (SO) or Board Resolution",
        'instructions' => "Issued to verified graduates of CTU degree programs."
    ]
];

foreach ($samples as $doc_name => $info) {
    $stmt = $conn->prepare("UPDATE document_types SET fee = ?, processing_time = ?, requirements = ?, instructions = ? WHERE document_name LIKE ? AND (requirements IS NULL OR requirements = '')");
    $search = "%" . $doc_name . "%";
    $stmt->bind_param("dssss", $info['fee'], $info['processing_time'], $info['requirements'], $info['instructions'], $search);
    $stmt->execute();
    $stmt->close();
}

