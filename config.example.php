<?php
/* ============================================================
   TASK DESK — SETTINGS (EXAMPLE)
   Copy this file to config.php and edit config.php.
   config.php is ignored by Git and must never be committed.

   Change the words between the ' ' quotes.
   Always keep the quotes and the ; at the end of each line.

   © 2026 Raptuner Distribution Private Limited.
   All rights reserved. No licence granted.
   ============================================================ */

/* ---- 1. PASSWORDS ----
   Replace both placeholders with your own strong, different passwords. */

$OWNER_PASSWORD     = 'YOUR_OWNER_PASSWORD';      // owner login
$ASSISTANT_PASSWORD = 'YOUR_ASSISTANT_PASSWORD';  // assistant login


/* ---- 2. NAMES ----
   Sample names. Shown in the greeting, buttons and activity log. */

$OWNER_NAME     = 'Alex';
$ASSISTANT_NAME = 'Sam';


/* ---- 3. STORE OPTIONS ----
   These are the buttons you tap when sending a task.
   Add, remove or reword them freely. First one is the default. */

$STORE_OPTIONS = array(
    'All stores',
    'JioSaavn only',
    'No Spotify & Apple',
    'YouTube only',
);


/* ---- 4. VERSION OPTIONS ---- */

$VERSION_OPTIONS = array(
    'Original',
    'Locked',
    'Both',
);

/* Nothing else to fill in. */
