<?php

require __DIR__ . '/includes/init.php';

if (!current_user()) {
    redirect('login.php');
}

redirect('dashboard.php');
