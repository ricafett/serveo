<?php

$livewire = require base_path('vendor/livewire/livewire/config/livewire.php');

$livewire['temporary_file_upload']['rules'] = ['required', 'file', 'max:512000'];

return $livewire;
