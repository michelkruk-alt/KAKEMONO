<?php
require_once __DIR__ . '/includes/bootstrap.php';
session_destroy();
session_start();
set_flash('success', 'Vous êtes déconnecté.');
redirect_to('/index.php');
