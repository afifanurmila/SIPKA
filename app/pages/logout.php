<?php
declare(strict_types=1);

if (is_post()) {
    csrf_check();
    if (current_user()) {
        log_act('logout', 'Keluar dari sistem');
    }
    $_SESSION = [];
    session_destroy();
}
redirect(url('login'));
