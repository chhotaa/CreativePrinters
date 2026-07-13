<?php
// Session-based flash messages, used with the POST-redirect-GET pattern so
// that reloading a page after a successful action doesn't resubmit the
// same form data and create a duplicate record.
function setFlashMessage(string $message): void
{
    $_SESSION['flash_message'] = $message;
}

function setFlashError(string $error): void
{
    $_SESSION['flash_error'] = $error;
}

function consumeFlashMessages(): array
{
    $message = $_SESSION['flash_message'] ?? '';
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_message'], $_SESSION['flash_error']);
    return [$message, $error];
}
