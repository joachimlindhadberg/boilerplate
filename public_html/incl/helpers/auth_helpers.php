<?php
/**
 * Shared authentication helpers.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['auth']['id']);
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int)$_SESSION['auth']['id'] : null;
}

function current_user(): ?array
{
    return is_logged_in() ? (array)$_SESSION['auth'] : null;
}
