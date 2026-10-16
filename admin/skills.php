<?php $cfg = ['table' => 'skills', 'title' => 'Skills', 'cols' => ['name', 'category', 'level'], 'fields' => [
    'name' => ['Skill name *', 'text', null], 'category' => ['Category (Frontend, Backend, Database, Tools, Design)', 'text', null], 'level' => ['Level (0-100)', 'number', null]]];
require __DIR__ . '/_crud.php';
