<?php $cfg = ['table' => 'experiences', 'title' => 'Experience & Education', 'cols' => ['type', 'period', 'title', 'organization'], 'fields' => [
    'title' => ['Title / role / degree *', 'text', null], 'organization' => ['Company / school', 'text', null], 'period' => ['Period (e.g. 2023 - 2025)', 'text', null],
    'type' => ['Type', 'select', ['work', 'education', 'internship', 'achievement']], 'description' => ['Description', 'textarea', null]]];
require __DIR__ . '/_crud.php';
