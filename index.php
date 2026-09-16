<?php
/* ============================================================
   TASK DESK — Raptuner
   One file. Do not edit. All settings live in config.php.
   Data is written to data/ which is created automatically.

   © 2026 Raptuner Distribution Private Limited.
   All rights reserved. No licence granted.
   ============================================================ */

session_start();
date_default_timezone_set('Asia/Kolkata');

$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) { exit('config.php is missing. Upload it next to index.php.'); }
require $cfg;

$DIR      = __DIR__ . '/data';
$F_TASKS  = $DIR . '/tasks.json.php';
$F_ACCS   = $DIR . '/accounts.json.php';
$UPDIR    = $DIR . '/files';
$GUARD    = "<?php http_response_code(403); die('no'); ?>\n";

/* ---------- storage ---------- */
function jread($file) {
    global $GUARD;
    if (!file_exists($file)) return null;
    $raw = file_get_contents($file);
    if (strpos($raw, '<?php') === 0) $raw = substr($raw, strlen($GUARD));
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}
function jwrite($file, $data) {
    global $GUARD;
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $GUARD . json_encode(array_values($data), JSON_UNESCAPED_UNICODE), LOCK_EX) === false) return false;
    return @rename($tmp, $file);
}

/* ---------- first run ---------- */
$fsReady = true;
if (!is_dir($DIR))   { $fsReady = @mkdir($DIR, 0755, true) && $fsReady; }
if (!is_dir($UPDIR)) { $fsReady = @mkdir($UPDIR, 0755, true) && $fsReady; }
if (is_dir($DIR) && !file_exists($DIR . '/.htaccess')) {
    @file_put_contents($DIR . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php\\d)$\">\nRequire all denied\n</FilesMatch>\n");
}

$SEED = [['TEST1','Sample Artist One'],['TEST2','Sample Artist Two'],['TEST3','Sample Artist Three'],['A10','Sample Artist Four'],['A11',''],['B20','Sample Artist Five'],['B21',''],['C30','Sample Artist Six'],['C31',''],['D40','Sample Artist Seven'],['D41',''],['E50','Sample Artist Eight']];

if (jread($F_ACCS) === null && is_dir($DIR)) {
    $seeded = array();
    foreach ($SEED as $s) $seeded[] = array('code' => $s[0], 'name' => $s[1]);
    jwrite($F_ACCS, $seeded);
}
if (jread($F_TASKS) === null && is_dir($DIR)) jwrite($F_TASKS, array());

/* ---------- login ---------- */
if (isset($_GET['logout'])) { session_destroy(); header('Location: .'); exit; }

$loginError = '';
if (isset($_POST['pw'])) {
    $p = (string)$_POST['pw'];
    if (hash_equals($OWNER_PASSWORD, $p))    { $_SESSION['role'] = 'owner'; }
    elseif (hash_equals($ASSISTANT_PASSWORD, $p)) { $_SESSION['role'] = 'assistant'; }
    else { $loginError = 'Wrong password. Try again.'; }
    if (!empty($_SESSION['role'])) {
        $_SESSION['token'] = bin2hex(random_bytes(16));
        header('Location: .'); exit;
    }
}

if (empty($_SESSION['role'])) {
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"><title>Task Desk</title>
    <meta name="theme-color" content="#12161B">
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&family=DM+Mono&display=swap" rel="stylesheet">
    <style>
      body{background:#E9ECF0;font-family:Archivo,system-ui,-apple-system,sans-serif;display:grid;
        place-items:center;min-height:100vh;margin:0;padding:20px;color:#12161B;-webkit-font-smoothing:antialiased}
      .box{background:#fff;border:1px solid #D3D9E0;border-radius:6px;padding:26px;width:100%;max-width:330px}
      h1{font-size:24px;font-weight:800;letter-spacing:-.03em;margin:0}
      p.sub{font-family:'DM Mono',monospace;font-size:10.5px;color:#6C7683;letter-spacing:.09em;
        text-transform:uppercase;margin:6px 0 20px}
      input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #D3D9E0;border-radius:4px;
        background:#E9ECF0;font-size:16px;font-family:inherit;color:#12161B}
      button{width:100%;margin-top:10px;padding:13px;background:#12161B;color:#fff;border:none;
        border-radius:4px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer}
      button:hover{opacity:.88}
      .err{color:#B4342C;font-size:13px;margin-top:11px}
    </style></head><body>
    <form class="box" method="post">
      <h1>Task Desk</h1><p class="sub">Raptuner</p>
      <input type="password" name="pw" placeholder="Password" autofocus autocomplete="current-password">
      <button type="submit">Sign in</button>
      <?php if ($loginError !== '') echo '<p class="err">' . htmlspecialchars($loginError) . '</p>'; ?>
    </form></body></html><?php
    exit;

}

$role    = $_SESSION['role'];
$isOwner = ($role === 'owner');
$me      = $isOwner ? $OWNER_NAME : $ASSISTANT_NAME;
if (empty($_SESSION['token'])) $_SESSION['token'] = bin2hex(random_bytes(16));
$TOKEN   = $_SESSION['token'];

/* ---------- api ---------- */
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_GET['api'];

    if ($act === 'upload') {
        if (!$isOwner || empty($_POST['token']) || !hash_equals($TOKEN, $_POST['token'])) {
            echo json_encode(array('ok' => false)); exit;
        }
        $out = array();
        if (!empty($_FILES['f'])) {
            $okExt = array('mp3','wav','m4a','ogg','webm','aac','flac','jpg','jpeg','png','webp','gif','pdf','txt');
            $n = count($_FILES['f']['name']);
            for ($i = 0; $i < $n; $i++) {
                if ($_FILES['f']['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($_FILES['f']['size'][$i] > 60 * 1024 * 1024) continue;
                $orig = $_FILES['f']['name'][$i];
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $okExt, true)) continue;
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
                $safe = substr($safe, 0, 60);
                $store = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (@move_uploaded_file($_FILES['f']['tmp_name'][$i], $UPDIR . '/' . $store)) {
                    $out[] = array('file' => $store, 'name' => $safe, 'ext' => $ext);
                }
            }
        }
        echo json_encode(array('ok' => true, 'files' => $out)); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) $in = array();
    if ($act !== 'list') {
        if (empty($in['token']) || !hash_equals($TOKEN, $in['token'])) {
            echo json_encode(array('ok' => false, 'error' => 'session expired — reload the page')); exit;
        }
    }

    $tasks = jread($F_TASKS); if ($tasks === null) $tasks = array();
    $accs  = jread($F_ACCS);  if ($accs  === null) $accs  = array();

    if ($act === 'list') {
        usort($tasks, function ($a, $b) {
            $rank = array('ask' => 0, 'pending' => 1, 'done' => 2);
            $ra = $rank[$a['state']]; $rb = $rank[$b['state']];
            if ($ra !== $rb) return $ra - $rb;
            if ($a['state'] !== 'done' && $a['urgent'] != $b['urgent']) return $b['urgent'] - $a['urgent'];
            return strcmp($b['updated'], $a['updated']);
        });
        echo json_encode(array('tasks' => $tasks, 'accounts' => $accs, 'me' => $me,
            'owner' => $isOwner, 'token' => $TOKEN, 'writable' => $fsReady)); exit;
    }

    if ($act === 'create' && $isOwner) {
        $code = strtoupper(trim(isset($in['code']) ? $in['code'] : ''));
        $code = preg_replace('/[^A-Z0-9]/', '', $code);
        if ($code === '') { echo json_encode(array('ok' => false, 'error' => 'no profile')); exit; }

        $found = false;
        foreach ($accs as $a) if ($a['code'] === $code) { $found = true; break; }
        if (!$found) { $accs[] = array('code' => $code, 'name' => ''); jwrite($F_ACCS, $accs); }

        $links = array();
        foreach (array_slice(isset($in['links']) ? $in['links'] : array(), 0, 40) as $l) {
            if (preg_match('#^https?://#i', $l)) $links[] = substr($l, 0, 500);
        }
        $files = array();
        foreach (array_slice(isset($in['files']) ? $in['files'] : array(), 0, 20) as $f) {
            if (!empty($f['file']) && preg_match('/^[A-Za-z0-9._-]+$/', $f['file'])) {
                $files[] = array(
                    'file' => $f['file'],
                    'name' => substr(isset($f['name']) ? $f['name'] : 'file', 0, 80),
                    'ext'  => isset($f['ext']) ? $f['ext'] : ''
                );
            }
        }
        $maxId = 0;
        foreach ($tasks as $t) if ((int)$t['id'] > $maxId) $maxId = (int)$t['id'];
        $now = date('Y-m-d H:i:s');
        $tasks[] = array(
            'id'      => $maxId + 1,
            'code'    => $code,
            'links'   => $links,
            'files'   => $files,
            'store'   => substr(isset($in['store']) ? $in['store'] : '', 0, 40),
            'version' => substr(isset($in['version']) ? $in['version'] : '', 0, 40),
            'note'    => substr(trim(isset($in['note']) ? $in['note'] : ''), 0, 2000),
            'urgent'  => !empty($in['urgent']) ? 1 : 0,
            'state'   => 'pending',
            'ask'     => '',
            'created' => $now,
            'updated' => $now,
            'log'     => array(array('t' => $now, 'w' => $me, 'a' => 'sent'))
        );
        if (!jwrite($F_TASKS, $tasks)) { echo json_encode(array('ok' => false, 'error' => 'cannot write')); exit; }
        echo json_encode(array('ok' => true)); exit;
    }

    if ($act === 'act') {
        $id  = (int)(isset($in['id']) ? $in['id'] : 0);
        $do  = isset($in['do']) ? $in['do'] : '';
        $txt = substr(trim(isset($in['text']) ? $in['text'] : ''), 0, 1000);
        $now = date('Y-m-d H:i:s');
        foreach ($tasks as $k => $t) {
            if ((int)$t['id'] !== $id) continue;
            if ($do === 'done') {
                $tasks[$k]['state'] = 'done'; $tasks[$k]['ask'] = '';
                $tasks[$k]['log'][] = array('t' => $now, 'w' => $me, 'a' => 'done');
            } elseif ($do === 'ask' && $txt !== '') {
                $tasks[$k]['state'] = 'ask'; $tasks[$k]['ask'] = $txt;
                $tasks[$k]['log'][] = array('t' => $now, 'w' => $me, 'a' => 'asked: ' . $txt);
            } elseif ($do === 'answer' && $isOwner && $txt !== '') {
                $tasks[$k]['state'] = 'pending'; $tasks[$k]['ask'] = '';
                $tasks[$k]['note'] = trim($tasks[$k]['note'] . "\n↳ " . $txt);
                $tasks[$k]['log'][] = array('t' => $now, 'w' => $me, 'a' => 'answered: ' . $txt);
            } elseif ($do === 'reopen' && $isOwner) {
                $tasks[$k]['state'] = 'pending';
                $tasks[$k]['log'][] = array('t' => $now, 'w' => $me, 'a' => 'reopened');
            } elseif ($do === 'urgent' && $isOwner) {
                $tasks[$k]['urgent'] = $tasks[$k]['urgent'] ? 0 : 1;
            } else {
                echo json_encode(array('ok' => false)); exit;
            }
            $tasks[$k]['updated'] = $now;
            jwrite($F_TASKS, $tasks);
            echo json_encode(array('ok' => true)); exit;
        }
        echo json_encode(array('ok' => false)); exit;
    }

    if ($act === 'delete' && $isOwner) {
        $id = (int)(isset($in['id']) ? $in['id'] : 0);
        $keep = array();
        foreach ($tasks as $t) if ((int)$t['id'] !== $id) $keep[] = $t;
        jwrite($F_TASKS, $keep);
        echo json_encode(array('ok' => true)); exit;
    }

    if ($act === 'rename' && $isOwner) {
        $code = strtoupper(trim(isset($in['code']) ? $in['code'] : ''));
        $name = substr(trim(isset($in['name']) ? $in['name'] : ''), 0, 60);
        foreach ($accs as $k => $a) if ($a['code'] === $code) { $accs[$k]['name'] = $name; }
        jwrite($F_ACCS, $accs);
        echo json_encode(array('ok' => true)); exit;
    }

    if ($act === 'addprofile' && $isOwner) {
        $code = strtoupper(trim(isset($in['code']) ? $in['code'] : ''));
        $code = preg_replace('/[^A-Z0-9]/', '', $code);
        $name = substr(trim(isset($in['name']) ? $in['name'] : ''), 0, 60);
        if ($code === '') { echo json_encode(array('ok' => false, 'error' => 'Enter a code')); exit; }
        foreach ($accs as $a) if ($a['code'] === $code) {
            echo json_encode(array('ok' => false, 'error' => $code . ' already exists')); exit;
        }
        $accs[] = array('code' => $code, 'name' => $name);
        if (!jwrite($F_ACCS, $accs)) { echo json_encode(array('ok' => false, 'error' => 'cannot write')); exit; }
        echo json_encode(array('ok' => true)); exit;
    }

    if ($act === 'delprofile' && $isOwner) {
        $code = strtoupper(trim(isset($in['code']) ? $in['code'] : ''));
        foreach ($tasks as $t) if ($t['code'] === $code) {
            echo json_encode(array('ok' => false, 'error' => 'This profile still has tasks')); exit;
        }
        $keep = array();
        foreach ($accs as $a) if ($a['code'] !== $code) $keep[] = $a;
        jwrite($F_ACCS, $keep);
        echo json_encode(array('ok' => true)); exit;
    }

    echo json_encode(array('ok' => false, 'error' => 'not allowed')); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Task Desk</title>
<meta name="theme-color" content="#F4F5F7">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#F4F5F7; --card:#fff; --ink:#15181D; --mid:#5A6472; --soft:#8C95A3;
    --line:#E6E9ED; --line2:#D8DDE4; --accent:#3B5BDB; --accent-soft:#EDF1FE;
    --pending:#B26A12; --pending-bg:#FDF2E3;
    --ask:#B4342C;    --ask-bg:#FBEAE8;
    --done:#12734F;   --done-bg:#E4F4EC;
    --r:10px; --rs:7px;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--ink);font-family:Archivo,system-ui,-apple-system,sans-serif;
    -webkit-font-smoothing:antialiased;font-size:14px}
  button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}
  a{color:inherit}
  :focus-visible{outline:2px solid var(--accent);outline-offset:2px}
  ::-webkit-scrollbar{width:9px;height:9px}
  ::-webkit-scrollbar-thumb{background:#CDD3DA;border-radius:9px}

  .app{display:grid;grid-template-columns:232px 1fr;min-height:100vh;gap:14px;padding:14px;max-width:1420px;margin:0 auto}

  /* ---------- sidebar ---------- */
  .side{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:18px 14px;
    display:flex;flex-direction:column;position:sticky;top:14px;height:calc(100vh - 28px)}
  .logo{font-size:21px;font-weight:800;letter-spacing:-.035em;line-height:1}
  .logo span{display:block;font-family:'DM Mono',monospace;font-size:10px;font-weight:400;
    letter-spacing:.11em;text-transform:uppercase;color:var(--soft);margin-top:7px}
  .nav{display:flex;flex-direction:column;gap:3px;margin-top:26px}
  .nav button{display:flex;align-items:center;gap:10px;padding:10px 11px;border-radius:var(--rs);
    font-size:14px;font-weight:500;text-align:left;color:var(--mid);width:100%}
  .nav button:hover{background:var(--bg);color:var(--ink)}
  .nav button[aria-current="true"]{background:var(--accent);color:#fff;font-weight:600}
  .nav .ic{width:17px;flex:none;opacity:.9;display:grid;place-items:center}
  .nav .ct{margin-left:auto;font-family:'DM Mono',monospace;font-size:11px;
    background:var(--bg);color:var(--mid);padding:2px 7px;border-radius:20px;min-width:22px;text-align:center}
  .nav button[aria-current="true"] .ct{background:rgba(255,255,255,.22);color:#fff}
  .nav .ct.hot{background:var(--ask-bg);color:var(--ask);font-weight:600}
  .side-sep{height:1px;background:var(--line);margin:20px 0 16px}
  .side-lbl{font-size:10px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;
    color:var(--soft);padding:0 11px;margin-bottom:9px}
  .side-foot{margin-top:auto;padding-top:16px;border-top:1px solid var(--line)}
  .whoami{display:flex;align-items:center;gap:9px;padding:0 3px}
  .av{width:31px;height:31px;border-radius:50%;background:var(--ink);color:#fff;font-weight:700;
    font-size:13px;display:grid;place-items:center;flex:none}
  .whoami b{font-size:13px;font-weight:600;display:block;line-height:1.25}
  .whoami small{font-size:11px;color:var(--soft)}
  .signout{display:block;margin-top:11px;font-size:12px;color:var(--soft);text-decoration:none;padding:5px 3px}
  .signout:hover{color:var(--ask)}

  /* ---------- main ---------- */
  .main{min-width:0;display:flex;flex-direction:column;gap:14px}
  .topbar{background:var(--card);border:1px solid var(--line);border-radius:var(--r);
    padding:9px 10px 9px 14px;display:flex;align-items:center;gap:10px}
  .sbox{flex:1;display:flex;align-items:center;gap:9px;min-width:0}
  .sbox svg{flex:none;color:var(--soft)}
  .sbox input{flex:1;border:none;background:none;font-family:inherit;font-size:16px;color:var(--ink);min-width:0}
  .sbox input::placeholder{color:var(--soft)}
  .sclear{color:var(--soft);font-size:18px;line-height:1;padding:0 4px}
  .sclear:hover{color:var(--ink)}
  .btn{background:var(--accent);color:#fff;padding:10px 15px;border-radius:var(--rs);
    font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:7px;white-space:nowrap}
  .btn:hover{filter:brightness(1.08)}
  .btn.ghost{background:var(--card);color:var(--ink);border:1px solid var(--line2)}
  .btn.ghost:hover{background:var(--bg);filter:none}

  .greet h2{font-size:27px;font-weight:800;letter-spacing:-.035em;line-height:1.15}
  .greet p{font-size:13px;color:var(--soft);margin-top:5px}

  .stats{background:var(--card);border:1px solid var(--line);border-radius:999px;
    padding:13px 8px;display:flex;flex-wrap:wrap;align-items:center}
  .stat{display:flex;align-items:center;gap:9px;padding:0 20px;position:relative;flex:none}
  .stat+.stat::before{content:"";position:absolute;left:0;top:50%;transform:translateY(-50%);
    width:1px;height:22px;background:var(--line)}
  .stat b{font-size:19px;font-weight:700;letter-spacing:-.02em}
  .stat span{font-size:13px;color:var(--mid)}
  .stat .bub{width:9px;height:9px;border-radius:50%;flex:none}

  .panel{background:var(--card);border:1px solid var(--line);border-radius:var(--r);overflow:hidden}
  .phead{display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:15px 17px;border-bottom:1px solid var(--line);flex-wrap:wrap}
  .phead h3{font-size:16px;font-weight:700;letter-spacing:-.02em;display:flex;align-items:center;gap:9px}
  .phead .sub{font-size:12.5px;color:var(--soft);font-weight:400}
  .segs{display:flex;gap:5px;flex-wrap:wrap}
  .seg{border:1px solid var(--line2);border-radius:20px;padding:6px 13px;font-size:12.5px;
    font-weight:500;color:var(--mid);display:flex;align-items:center;gap:7px}
  .seg:hover{border-color:var(--ink);color:var(--ink)}
  .seg[aria-pressed="true"]{background:var(--ink);border-color:var(--ink);color:#fff;font-weight:600}
  .seg .n{font-family:'DM Mono',monospace;font-size:11px;opacity:.7}

  /* ---------- rows ---------- */
  .rows{display:flex;flex-direction:column}
  .row{display:grid;grid-template-columns:1fr auto auto;gap:14px;align-items:center;
    padding:13px 17px;border-bottom:1px solid var(--line);cursor:pointer}
  .row:last-child{border-bottom:none}
  .row:hover{background:#FAFBFC}
  .row.open{background:#FAFBFC}
  .rmain{min-width:0}
  .rtop{display:flex;align-items:baseline;gap:9px;flex-wrap:wrap}
  .pcode{font-family:'DM Mono',monospace;font-weight:500;font-size:15px;letter-spacing:-.01em;flex:none}
  .pname{font-size:14px;font-weight:600;letter-spacing:-.01em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .pname.none{color:var(--soft);font-weight:400;font-style:italic;font-size:13px}
  .rsub{font-size:12.5px;color:var(--soft);margin-top:4px;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap}
  .rsub .sep{opacity:.45;margin:0 7px}
  .pill{font-size:11.5px;font-weight:600;padding:5px 11px;border-radius:20px;white-space:nowrap}
  .p-pending{background:var(--pending-bg);color:var(--pending)}
  .p-ask{background:var(--ask-bg);color:var(--ask)}
  .p-done{background:var(--done-bg);color:var(--done)}
  .p-urg{background:var(--ask);color:#fff}
  .p-flat{background:var(--bg);color:var(--mid)}
  .rtime{font-family:'DM Mono',monospace;font-size:11px;color:var(--soft);white-space:nowrap}
  .chev{color:var(--soft);font-size:12px;transition:transform .15s}
  .row.open .chev{transform:rotate(180deg)}

  .detail{padding:2px 17px 17px;border-bottom:1px solid var(--line);background:#FAFBFC}
  .detail .note{font-size:14px;line-height:1.5;white-space:pre-wrap;margin-bottom:12px}
  .dtags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
  .tag{font-size:11.5px;font-weight:600;padding:5px 10px;border-radius:5px}
  .t-store{background:var(--accent-soft);color:#2B4299}
  .t-ver{background:#F2EBFB;color:#5B2FA8}
  .links{list-style:none;display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
  .links a{font-family:'DM Mono',monospace;font-size:12px;text-decoration:none;background:var(--card);
    border:1px solid var(--line);padding:9px 11px;border-radius:var(--rs);
    display:flex;justify-content:space-between;gap:10px}
  .links a:hover{border-color:var(--accent);color:var(--accent)}
  .links em{font-style:normal;color:var(--soft)}
  .fl{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
  .fl audio{width:100%;height:36px}
  .fl img{max-width:260px;border-radius:var(--rs);border:1px solid var(--line);display:block}
  .filerow{background:var(--card);border:1px solid var(--line);padding:9px 11px;border-radius:var(--rs);font-size:12.5px}
  .filerow a{text-decoration:none}
  .filerow a:hover{color:var(--accent)}
  .asked{background:var(--ask-bg);border:1px solid #EFCBC6;border-radius:var(--rs);
    padding:11px 13px;font-size:13.5px;line-height:1.45;margin-bottom:12px;white-space:pre-wrap}
  .asked b{display:block;font-size:10px;letter-spacing:.1em;text-transform:uppercase;
    color:var(--ask);margin-bottom:4px}
  .dacts{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
  .ba{border:1px solid var(--line2);background:var(--card);border-radius:var(--rs);
    padding:9px 14px;font-size:13px;font-weight:600}
  .ba:hover{border-color:var(--ink)}
  .ba.done{border-color:var(--done);color:var(--done)}
  .ba.done:hover{background:var(--done);color:#fff}
  .ba.ask{border-color:var(--ask);color:var(--ask)}
  .ba.ask:hover{background:var(--ask);color:#fff}
  .ba.dim{color:var(--soft)}
  .ba.dim:hover{border-color:var(--ask);color:var(--ask)}
  .log{font-family:'DM Mono',monospace;font-size:11px;color:var(--soft);margin-top:12px;line-height:1.7}


  /* ---------- view switcher ---------- */
  .viewbar{background:var(--card);border:1px solid var(--line);border-radius:var(--r);
    padding:6px;display:flex;gap:4px;overflow-x:auto}
  .vb{flex:1;min-width:112px;border-radius:var(--rs);padding:11px 10px;font-size:13.5px;
    font-weight:600;color:var(--mid);display:flex;align-items:center;justify-content:center;gap:8px;
    white-space:nowrap}
  .vb:hover{background:var(--bg);color:var(--ink)}
  .vb[aria-pressed="true"]{background:var(--accent);color:#fff}
  .vb .n{font-family:'DM Mono',monospace;font-size:11px;background:rgba(0,0,0,.07);
    padding:2px 7px;border-radius:20px}
  .vb[aria-pressed="true"] .n{background:rgba(255,255,255,.24)}
  .vb .n.hot{background:var(--ask-bg);color:var(--ask);font-weight:700}
  .vb[aria-pressed="true"] .n.hot{background:rgba(255,255,255,.24);color:#fff}

  /* ---------- dashboard ---------- */
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
  .seeall{font-size:12.5px;font-weight:600;color:var(--accent);padding:6px 10px;border-radius:20px}
  .seeall:hover{background:var(--accent-soft)}
  .mini-row{display:flex;align-items:center;gap:12px;padding:12px 17px;
    border-bottom:1px solid var(--line);cursor:pointer}
  .mini-row:last-child{border-bottom:none}
  .mini-row:hover{background:#FAFBFC}
  .mini-row .mm{flex:1;min-width:0}
  .mini-row .mt{font-size:13.5px;font-weight:600;letter-spacing:-.01em;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap}
  .mini-row .ms{font-size:12px;color:var(--soft);margin-top:3px;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap}
  .dotcol{width:8px;height:8px;border-radius:50%;flex:none}
  .qcard{padding:14px 17px;border-bottom:1px solid var(--line)}
  .qcard:last-child{border-bottom:none}
  .qcard .qh{display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:600}
  .qcard .qb{font-size:13px;line-height:1.5;color:var(--mid);margin-top:6px;white-space:pre-wrap}
  .qcard .qa{margin-top:10px}

  /* ---------- profile row extras ---------- */
  .rbtns{display:flex;gap:5px;flex:none}
  .iconb{width:31px;height:31px;border:1px solid var(--line2);border-radius:var(--rs);
    display:grid;place-items:center;color:var(--soft);font-size:13px;background:var(--card)}
  .iconb:hover{border-color:var(--accent);color:var(--accent)}

  .empty{padding:50px 20px;text-align:center;color:var(--soft);font-size:13.5px;line-height:1.6}
  .empty b{display:block;color:var(--mid);font-size:15px;font-weight:600;margin-bottom:6px}

  /* ---------- profile drill-down ---------- */
  .back{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--mid);
    font-weight:500;padding:4px 0;margin-bottom:2px}
  .back:hover{color:var(--ink)}
  .phero{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;
    padding:17px;border-bottom:1px solid var(--line)}
  .phero .big{font-family:'DM Mono',monospace;font-size:26px;font-weight:500;letter-spacing:-.02em;line-height:1}
  .phero .nm{font-size:16px;font-weight:600;margin-top:7px}
  .phero .nm.none{color:var(--soft);font-style:italic;font-weight:400}
  .phero .mini{display:flex;gap:16px;margin-top:11px}
  .phero .mini div{font-size:12.5px;color:var(--soft)}
  .phero .mini b{display:block;font-size:17px;font-weight:700;color:var(--ink);letter-spacing:-.02em}

  /* ---------- modal ---------- */
  .scrim{position:fixed;inset:0;background:rgba(21,24,29,.5);display:none;place-items:center;
    padding:14px;z-index:60;overflow:auto}
  .scrim.open{display:grid}
  .sheet{background:var(--card);border-radius:12px;width:100%;max-width:530px;max-height:92vh;
    overflow:auto;padding:22px}
  .sheet h3{font-size:19px;font-weight:800;letter-spacing:-.025em}
  .sheet .sub{font-size:13px;color:var(--soft);margin-top:5px;line-height:1.5}
  label{display:block;font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
    color:var(--soft);margin:18px 0 8px}
  textarea,.field{width:100%;background:var(--bg);border:1px solid var(--line2);border-radius:var(--rs);
    padding:11px 12px;font-size:16px;color:var(--ink);font-family:Archivo,sans-serif}
  textarea{font-family:'DM Mono',monospace;font-size:15px;line-height:1.6;resize:vertical}
  .field:focus,textarea:focus{border-color:var(--accent);outline:none}
  #f-acc,#np-code{font-family:'DM Mono',monospace;text-transform:uppercase}
  .accwrap{position:relative}
  .drop{position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid var(--line2);
    border-radius:var(--rs);max-height:210px;overflow:auto;z-index:5;display:none;
    box-shadow:0 10px 26px rgba(21,24,29,.13)}
  .drop.open{display:block}
  .drop button{display:flex;width:100%;gap:10px;align-items:baseline;padding:10px 12px;text-align:left;font-size:13.5px}
  .drop button:hover,.drop button.hi{background:var(--bg)}
  .drop .c{font-family:'DM Mono',monospace;font-weight:500;min-width:50px}
  .drop .nm{color:var(--soft);font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .drop .mk{margin-left:auto;font-size:11px;color:var(--soft);font-family:'DM Mono',monospace}
  .opts{display:flex;gap:7px;flex-wrap:wrap}
  .opt{border:1px solid var(--line2);border-radius:var(--rs);padding:10px 13px;font-size:13.5px;
    font-weight:500;background:var(--bg)}
  .opt:hover{border-color:var(--ink)}
  .opt[aria-pressed="true"]{background:var(--ink);color:#fff;border-color:var(--ink)}
  .opt.warn[aria-pressed="true"]{background:var(--ask);border-color:var(--ask)}
  .hintline{font-family:'DM Mono',monospace;font-size:11.5px;color:var(--soft);margin-top:8px}
  .filebar{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
  .filebtn{border:1px dashed var(--line2);border-radius:var(--rs);padding:10px 13px;
    font-size:13.5px;font-weight:500;background:var(--bg)}
  .filebtn:hover{border-color:var(--ink)}
  .filebtn.rec{border-style:solid;border-color:var(--ask);color:var(--ask);background:var(--ask-bg)}
  .picked{list-style:none;margin-top:9px;display:flex;flex-direction:column;gap:6px}
  .picked li{display:flex;justify-content:space-between;gap:10px;background:var(--bg);
    padding:9px 11px;border-radius:var(--rs);font-size:12.5px;align-items:center}
  .picked button{color:var(--soft);font-size:17px;line-height:1}
  .picked button:hover{color:var(--ask)}
  .sheet-foot{display:flex;gap:8px;justify-content:flex-end;margin-top:24px;flex-wrap:wrap}

  .toast{position:fixed;left:50%;bottom:20px;transform:translateX(-50%);background:var(--ink);color:#fff;
    padding:12px 18px;border-radius:var(--rs);font-size:13.5px;opacity:0;pointer-events:none;
    transition:opacity .18s;z-index:80;max-width:90vw;text-align:center;
    box-shadow:0 8px 24px rgba(21,24,29,.22)}
  .toast.show{opacity:1}
  .warnbar{background:#FDF2E3;border:1px solid #E8C89A;border-radius:var(--r);
    padding:12px 14px;font-size:13.5px;line-height:1.5}

  @media(max-width:880px){
    .app{grid-template-columns:1fr;padding:10px;gap:11px}
    .side{position:static;height:auto;flex-direction:row;align-items:center;gap:12px;
      padding:11px 12px;overflow-x:auto}
    .logo{font-size:17px;flex:none}
    .logo span{display:none}
    .nav{flex-direction:row;margin-top:0;gap:4px;flex:1}
    .nav button{padding:8px 11px;font-size:13px;white-space:nowrap}
    .nav .ic{display:none}
    .side-sep,.side-lbl{display:none}
    .side-foot{margin-top:0;padding-top:0;border:none;flex:none}
    .whoami b,.whoami small{display:none}
    .signout{display:none}
    .greet h2{font-size:22px}
    .stats{border-radius:var(--r);padding:11px 4px}
    .stat{padding:0 13px}
    .stat b{font-size:16px}
    .stat span{font-size:12px}
    .row{grid-template-columns:1fr auto;gap:10px;padding:12px 13px}
    .chev{display:none}

    .grid2{grid-template-columns:1fr}
    .vb{min-width:auto;padding:10px 8px;font-size:12.5px}
    .vb .n{display:none}
    .phead{padding:13px}
    .detail{padding:2px 13px 15px}
  }
  @media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>

<div class="app">
  <aside class="side">
    <div class="logo">Task Desk<span>Raptuner</span></div>

    <nav class="nav" id="nav">
      <button data-nav="home" aria-current="true">
        <span class="ic">⬚</span>Dashboard</button>
      <button data-nav="tasks">
        <span class="ic">☰</span>Tasks<span class="ct" id="ct-tasks">0</span></button>
      <button data-nav="profiles">
        <span class="ic">▤</span>Profiles<span class="ct" id="ct-prof">0</span></button>
      <button data-nav="questions">
        <span class="ic">?</span>Questions<span class="ct" id="ct-ask">0</span></button>
      <button data-nav="done">
        <span class="ic">✓</span>Completed<span class="ct" id="ct-done">0</span></button>
    </nav>

    <div class="side-sep"></div>
    <div class="side-lbl">Shortcuts</div>
    <nav class="nav">
      <button data-nav="urgent"><span class="ic">!</span>Urgent<span class="ct" id="ct-urg">0</span></button>
      <button data-nav="noname"><span class="ic">✎</span>Unnamed<span class="ct" id="ct-noname">0</span></button>
    </nav>

    <div class="side-foot">
      <div class="whoami">
        <div class="av"><?php echo htmlspecialchars(strtoupper(substr($me, 0, 1))); ?></div>
        <div><b><?php echo htmlspecialchars($me); ?></b>
        <small><?php echo $isOwner ? 'Owner' : 'Team'; ?></small></div>
      </div>
      <a class="signout" href="?logout=1">Sign out</a>
    </div>
  </aside>

  <div class="main">
    <?php if (!$fsReady): ?>
    <p class="warnbar"><b>Cannot save yet.</b> In Hostinger File Manager, right-click the folder holding
    these files → Permissions → set 755 → tick "apply to subfolders".</p>
    <?php endif; ?>

    <div class="topbar">
      <div class="sbox">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>
        <input id="search" type="search" placeholder="Search profile, artist, note or question" aria-label="Search">
        <button class="sclear" id="sclear" hidden aria-label="Clear search">×</button>
      </div>
      <?php if ($isOwner): ?>
      <button class="btn ghost" id="addprof-btn">+ Profile</button>
      <button class="btn" id="new-btn">+ New task</button>
      <?php endif; ?>
    </div>

    <div class="greet">
      <h2 id="greet-h">Hello</h2>
      <p id="greet-p"></p>
    </div>

    <div class="stats" id="stats"></div>

    <div class="viewbar" id="viewbar">
      <button class="vb" data-nav="home">Dashboard</button>
      <button class="vb" data-nav="profiles">Profiles<span class="n" id="vb-prof">0</span></button>
      <button class="vb" data-nav="tasks">Tasks<span class="n" id="vb-tasks">0</span></button>
      <button class="vb" data-nav="done">Completed<span class="n" id="vb-done">0</span></button>
    </div>

    <section id="view"></section>
  </div>
</div>

<?php if ($isOwner): ?>
<div class="scrim" id="scrim" role="dialog" aria-modal="true" aria-labelledby="sheet-h">
  <div class="sheet">
    <h3 id="sheet-h">New task</h3>
    <p class="sub">Profile, links, stores, version. Everything else is optional.</p>

    <label for="f-acc">Profile</label>
    <div class="accwrap">
      <input class="field" id="f-acc" placeholder="TEST1" autocomplete="off" spellcheck="false">
      <div class="drop" id="drop"></div>
    </div>
    <p class="hintline" id="accname">Type a code or an artist name</p>

    <label for="f-links">Video links</label>
    <textarea id="f-links" rows="3" placeholder="Paste one or more YouTube links"></textarea>
    <p class="hintline" id="linkcount">No links yet</p>

    <label>Stores</label>
    <div class="opts" id="opt-store"></div>

    <label>Version</label>
    <div class="opts" id="opt-ver"></div>

    <label for="f-note">Note</label>
    <input class="field" id="f-note" placeholder="Remove track 5">

    <label>Attach</label>
    <div class="filebar">
      <button class="filebtn" id="pick-btn">Choose file</button>
      <button class="filebtn" id="rec-btn">Record voice note</button>
      <button class="opt warn" id="urg-btn" aria-pressed="false">Urgent</button>
      <input type="file" id="f-file" multiple hidden
             accept=".mp3,.wav,.m4a,.ogg,.aac,.flac,.jpg,.jpeg,.png,.webp,.gif,.pdf,.txt">
    </div>
    <ul class="picked" id="picked"></ul>

    <div class="sheet-foot">
      <button class="ba" id="cancel-btn">Cancel</button>
      <button class="btn" id="save-btn">Send to <?php echo htmlspecialchars($ASSISTANT_NAME); ?></button>
    </div>
  </div>
</div>

<div class="scrim" id="pscrim" role="dialog" aria-modal="true" aria-labelledby="np-h">
  <div class="sheet" style="max-width:430px">
    <h3 id="np-h">Add profile</h3>
    <p class="sub" id="np-sub">A profile is one artist account. The code is how you refer to it.</p>
    <label for="np-code">Code</label>
    <input class="field" id="np-code" placeholder="TEST4" autocomplete="off" spellcheck="false">
    <label for="np-name">Artist name</label>
    <input class="field" id="np-name" placeholder="Sample Artist">
    <div class="sheet-foot">
      <button class="ba" id="np-cancel">Cancel</button>
      <button class="btn" id="np-save">Add profile</button>
    </div>
  </div>
</div>

<?php endif; ?>

<div class="toast" id="toast" role="status"></div>

<script>
const IS_OWNER = <?php echo $isOwner ? 'true' : 'false'; ?>;
const ME = <?php echo json_encode($me); ?>;
const ASSISTANT_NAME = <?php echo json_encode($ASSISTANT_NAME); ?>;
let TOKEN = <?php echo json_encode($TOKEN); ?>;
const STORES = <?php echo json_encode($STORE_OPTIONS); ?>;
const VERSIONS = <?php echo json_encode($VERSION_OPTIONS); ?>;
</script>
<script>
const AUDIO = ['mp3','wav','m4a','ogg','webm','aac','flac'];
const IMG   = ['jpg','jpeg','png','webp','gif'];
const LABEL = {pending:'Pending', ask:'Question', done:'Done'};

let tasks = [], accounts = [], nav = 'home', sub = 'all', query = '',
    openProfile = null, expanded = {}, lastOpen = -1;

const $ = id => document.getElementById(id);
const accOf = c => accounts.find(a => a.code === c);
const vid = u => (u.match(/(?:v=|youtu\.be\/)([\w-]{6,})/) || [,u])[1];
const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g,
  c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const codeKey = c => [ (c.match(/^[A-Z]+/) || [''])[0], parseInt((c.match(/\d+/) || [0])[0], 10) ];

function when(d){
  const x = new Date(String(d).replace(' ','T'));
  const m = (Date.now() - x.getTime()) / 60000;
  if (m < 1) return 'just now';
  if (m < 60) return Math.floor(m) + 'm ago';
  if (m < 1440) return Math.floor(m/60) + 'h ago';
  if (m < 10080) return Math.floor(m/1440) + 'd ago';
  return x.toLocaleDateString(undefined, {day:'numeric', month:'short'});
}
function toast(m){
  const t = $('toast'); t.textContent = m; t.classList.add('show');
  clearTimeout(t._x); t._x = setTimeout(() => t.classList.remove('show'), 2500);
}
const api = (action, body) => fetch('?api=' + action, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(Object.assign({token: TOKEN}, body || {}))
  }).then(r => r.json()).catch(() => ({ok:false, error:'network problem'}));

/* ================= search ================= */
function hit(t){
  const q = query.trim().toLowerCase();
  if (!q) return true;
  const a = accOf(t.code);
  return t.code.toLowerCase().includes(q)
      || (a && a.name && a.name.toLowerCase().includes(q))
      || (t.note || '').toLowerCase().includes(q)
      || (t.ask || '').toLowerCase().includes(q)
      || (t.store || '').toLowerCase().includes(q)
      || (t.version || '').toLowerCase().includes(q);
}
const found = () => tasks.filter(hit);

function profHit(a){
  const q = query.trim().toLowerCase();
  if (!q) return true;
  return a.code.toLowerCase().includes(q) || (a.name || '').toLowerCase().includes(q);
}

/* ================= counts ================= */
function counts(){
  return {
    tasks: tasks.filter(t => t.state !== 'done').length,
    ask: tasks.filter(t => t.state === 'ask').length,
    pending: tasks.filter(t => t.state === 'pending').length,
    done: tasks.filter(t => t.state === 'done').length,
    urgent: tasks.filter(t => t.urgent && t.state !== 'done').length,
    prof: accounts.length,
    noname: accounts.filter(a => !a.name).length
  };
}
function paintChrome(){
  const c = counts();
  $('ct-tasks').textContent = c.tasks;
  $('ct-prof').textContent  = c.prof;
  $('ct-ask').textContent   = c.ask;
  $('ct-done').textContent  = c.done;
  $('ct-urg').textContent   = c.urgent;
  $('ct-noname').textContent = c.noname;
  $('ct-ask').className = 'ct' + (c.ask ? ' hot' : '');
  $('ct-urg').className = 'ct' + (c.urgent ? ' hot' : '');

  const h = new Date().getHours();
  const part = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  $('greet-h').textContent = part + ', ' + ME;
  $('greet-p').textContent = c.tasks
    ? c.tasks + ' task' + (c.tasks===1?'':'s') + ' open' + (c.ask ? ' · ' + c.ask + ' waiting on you' : '')
    : 'Everything is clear right now.';

  $('stats').innerHTML = [
    ['', c.prof, 'Profiles'],
    ['var(--pending)', c.pending, 'Pending'],
    ['var(--ask)', c.ask, 'Questions'],
    ['var(--done)', c.done, 'Completed']
  ].map(s => `<div class="stat">${s[0] ? `<span class="bub" style="background:${s[0]}"></span>` : ''}
      <b>${s[1]}</b><span>${s[2]}</span></div>`).join('');

  const vt = $('vb-tasks'), vp = $('vb-prof'), vd = $('vb-done');
  if (vt) vt.textContent = c.tasks;
  if (vp) vp.textContent = c.prof;
  if (vd) vd.textContent = c.done;
  document.querySelectorAll('#nav button, .nav button').forEach(b =>
    b.setAttribute('aria-current', b.dataset.nav === nav && !openProfile));
  document.querySelectorAll('.vb').forEach(b =>
    b.setAttribute('aria-pressed', b.dataset.nav === nav && !openProfile));
  document.title = (c.tasks ? '(' + c.tasks + ') ' : '') + 'Task Desk';
  $('sclear').hidden = !query;
}

/* ================= pieces ================= */
function pill(t){
  if (t.state === 'done') return '<span class="pill p-done">Done</span>';
  if (t.state === 'ask')  return '<span class="pill p-ask">Question</span>';
  return '<span class="pill p-pending">Pending</span>';
}
function fileHTML(f){
  const url = 'data/files/' + encodeURIComponent(f.file);
  if (AUDIO.indexOf(f.ext) > -1) return `<audio controls preload="none" src="${url}"></audio>`;
  if (IMG.indexOf(f.ext) > -1)
    return `<a href="${url}" target="_blank" rel="noopener"><img src="${url}" alt="${esc(f.name)}" loading="lazy"></a>`;
  return `<div class="filerow"><a href="${url}" target="_blank" rel="noopener" download>${esc(f.name)} ↓</a></div>`;
}
function subline(t){
  const bits = [];
  if (t.note) bits.push(esc(t.note.split('\n')[0]));
  if (t.store) bits.push(esc(t.store));
  if ((t.links||[]).length) bits.push(t.links.length + ' link' + (t.links.length>1?'s':''));
  if ((t.files||[]).length) bits.push(t.files.length + ' file' + (t.files.length>1?'s':''));
  if (!bits.length) bits.push('<span style="opacity:.7">no details</span>');
  return bits.join('<span class="sep">·</span>');
}

function taskRow(t, hideProfile){
  const a = accOf(t.code) || {name:''};
  const ex = expanded[t.id];
  return `<div class="row ${ex?'open':''}" data-task="${t.id}">
    <div class="rmain">
      <div class="rtop">
        ${hideProfile ? '' : `<span class="pcode">${esc(t.code)}</span>
        <span class="pname ${a.name?'':'none'}">${a.name ? esc(a.name) : 'unnamed'}</span>`}
        ${t.urgent && t.state!=='done' ? '<span class="pill p-urg">Urgent</span>' : ''}
      </div>
      <div class="rsub">${subline(t)}</div>
    </div>
    <div style="display:flex;align-items:center;gap:11px">
      ${pill(t)}<span class="rtime">${when(t.updated)}</span>
    </div>
    <span class="chev">▾</span>
  </div>
  ${ex ? taskDetail(t) : ''}`;
}

function taskDetail(t){
  const links = (t.links||[]).length
    ? `<ul class="links">${t.links.map(u =>
        `<li><a href="${esc(u)}" target="_blank" rel="noopener">${esc(vid(u))}<em>open ↗</em></a></li>`).join('')}</ul>` : '';
  const files = (t.files||[]).length ? `<div class="fl">${t.files.map(fileHTML).join('')}</div>` : '';
  const tags = [t.store ? `<span class="tag t-store">${esc(t.store)}</span>` : '',
                t.version ? `<span class="tag t-ver">${esc(t.version)}</span>` : ''].join('');

  let acts = '';
  if (!IS_OWNER && t.state !== 'done')
    acts = `<button class="ba ask" data-ask="${t.id}">Ask a question</button>
            <button class="ba done" data-done="${t.id}">Mark done</button>`;
  else if (IS_OWNER) {
    if (t.state === 'ask') acts = `<button class="ba ask" data-answer="${t.id}">Answer ${esc(ASSISTANT_NAME)}</button>`;
    else if (t.state === 'done') acts = `<button class="ba" data-reopen="${t.id}">Reopen</button>`;
    else acts = `<button class="ba done" data-done="${t.id}">Mark done</button>
                 <button class="ba" data-urg="${t.id}">${t.urgent ? 'Not urgent' : 'Mark urgent'}</button>`;
    acts += `<button class="ba" data-copy="${t.id}">Copy</button>
             <button class="ba dim" data-del="${t.id}">Delete</button>`;
  } else acts = `<button class="ba" data-copy="${t.id}">Copy</button>`;

  const log = (t.log||[]).slice(-4).map(l =>
    `${when(l.t)} — ${esc(l.w)} ${esc(l.a)}`).join('<br>');

  return `<div class="detail">
    ${tags ? `<div class="dtags">${tags}</div>` : ''}
    ${t.note ? `<p class="note">${esc(t.note)}</p>` : ''}
    ${links}${files}
    ${t.ask ? `<div class="asked"><b>${IS_OWNER ? esc(ASSISTANT_NAME)+' asked' : 'You asked'}</b>${esc(t.ask)}</div>` : ''}
    <div class="dacts">${acts}</div>
    ${log ? `<div class="log">${log}</div>` : ''}
  </div>`;
}

/* ================= dashboard ================= */
function viewHome(){
  const f = found();
  const attention = f.filter(t => t.state === 'ask')
    .concat(f.filter(t => t.urgent && t.state === 'pending'))
    .slice(0, 6);
  const recent = f.filter(t => t.state !== 'done')
    .slice(0, 6);

  const active = accounts.filter(profHit).map(a => ({a, s: profStats(a.code)}))
    .filter(x => x.s.open > 0)
    .sort((x, y) => y.s.open - x.s.open)
    .slice(0, 6);

  const questions = f.filter(t => t.state === 'ask').slice(0, 4);

  const attRows = attention.length ? attention.map(t => {
    const a = accOf(t.code) || {name:''};
    const col = t.state === 'ask' ? 'var(--ask)' : 'var(--pending)';
    return `<div class="mini-row" data-goto="${t.id}">
      <span class="dotcol" style="background:${col}"></span>
      <div class="mm">
        <div class="mt">${esc(t.code)} · ${a.name ? esc(a.name) : 'unnamed'}</div>
        <div class="ms">${t.state === 'ask' ? esc(t.ask) : (esc(t.note) || 'no note')}</div>
      </div>
      ${t.state === 'ask' ? '<span class="pill p-ask">Question</span>'
        : '<span class="pill p-urg">Urgent</span>'}
    </div>`;
  }).join('') : '';

  const openRows = recent.length ? recent.map(t => {
    const a = accOf(t.code) || {name:''};
    return `<div class="mini-row" data-goto="${t.id}">
      <span class="dotcol" style="background:${t.state==='ask'?'var(--ask)':'var(--pending)'}"></span>
      <div class="mm">
        <div class="mt">${esc(t.code)} · ${a.name ? esc(a.name) : 'unnamed'}</div>
        <div class="ms">${subline(t)}</div>
      </div>
      ${pill(t)}<span class="rtime">${when(t.updated)}</span>
    </div>`;
  }).join('') : '';

  const profRows = active.length ? active.map(x => `
    <div class="mini-row" data-prof="${esc(x.a.code)}">
      <div class="mm">
        <div class="mt">${esc(x.a.code)} · ${x.a.name ? esc(x.a.name) : '<span style="color:var(--soft);font-weight:400">unnamed</span>'}</div>
        <div class="ms">${x.s.total} total<span class="sep">·</span>${x.s.done} done</div>
      </div>
      <span class="pill p-pending">${x.s.open} open</span>
    </div>`).join('') : '';

  const qCards = questions.length ? questions.map(t => {
    const a = accOf(t.code) || {name:''};
    return `<div class="qcard">
      <div class="qh"><span class="dotcol" style="background:var(--ask)"></span>
        ${esc(t.code)} · ${a.name ? esc(a.name) : 'unnamed'}</div>
      <div class="qb">${esc(t.ask)}</div>
      ${IS_OWNER ? `<div class="qa"><button class="ba ask" data-answer="${t.id}">Answer ${esc(ASSISTANT_NAME)}</button></div>` : ''}
    </div>`;
  }).join('') : '';

  return `
  ${attention.length ? `<div class="panel" style="margin-bottom:14px">
    <div class="phead"><h3>Needs your attention <span class="sub">${attention.length}</span></h3>
      <button class="seeall" data-jump="questions">See all</button></div>
    ${attRows}
  </div>` : ''}

  <div class="panel" style="margin-bottom:14px">
    <div class="phead">
      <h3>Open tasks <span class="sub">${recent.length ? 'most recent' : ''}</span></h3>
      <button class="seeall" data-jump="tasks">See all</button>
    </div>
    ${openRows || emptyBox('No open tasks.',
        IS_OWNER ? 'Pick a profile below, or use + New task.' : 'You are all caught up.')}
  </div>

  <div class="grid2">
    <div class="panel">
      <div class="phead"><h3>Profiles with work</h3>
        <button class="seeall" data-jump="profiles">All ${accounts.length}</button></div>
      ${profRows || emptyBox('No profile has open work.', 'Everything is clear right now.')}
    </div>
    <div class="panel">
      <div class="phead"><h3>Questions from ${esc(ASSISTANT_NAME)}</h3></div>
      ${qCards || emptyBox('No questions.', esc(ASSISTANT_NAME) + ' has not asked anything.')}
    </div>
  </div>`;
}

/* ================= views ================= */
function viewTasks(){
  const f = found();
  const sets = {
    all:     f.filter(t => t.state !== 'done'),
    pending: f.filter(t => t.state === 'pending'),
    ask:     f.filter(t => t.state === 'ask'),
    urgent:  f.filter(t => t.urgent && t.state !== 'done')
  };
  const list = sets[sub] || sets.all;
  const segs = [['all','Open'],['pending','Pending'],['ask','Questions'],['urgent','Urgent']]
    .map(s => `<button class="seg" data-sub="${s[0]}" aria-pressed="${sub===s[0]}">
      ${s[1]}<span class="n">${sets[s[0]].length}</span></button>`).join('');

  return `<div class="panel">
    <div class="phead">
      <h3>Tasks ${query ? `<span class="sub">matching "${esc(query)}"</span>` : ''}</h3>
      <div class="segs">${segs}</div>
    </div>
    ${list.length ? `<div class="rows">${list.map(t => taskRow(t, false)).join('')}</div>`
      : emptyBox(query ? 'Nothing matches that search.' : 'No open tasks right now.',
                 query ? 'Try a profile code, an artist name or a word from a note.'
                       : (IS_OWNER ? 'Use + New task to send one.' : 'You are all caught up.'))}
  </div>`;
}

function viewDone(){
  const list = found().filter(t => t.state === 'done');
  return `<div class="panel">
    <div class="phead"><h3>Completed <span class="sub">${list.length} task${list.length===1?'':'s'}</span></h3></div>
    ${list.length ? `<div class="rows">${list.map(t => taskRow(t, false)).join('')}</div>`
      : emptyBox('Nothing completed yet.', 'Finished tasks are archived here automatically.')}
  </div>`;
}

function viewQuestions(){
  const list = found().filter(t => t.state === 'ask');
  return `<div class="panel">
    <div class="phead"><h3>Questions <span class="sub">waiting for an answer</span></h3></div>
    ${list.length ? `<div class="rows">${list.map(t => taskRow(t, false)).join('')}</div>`
      : emptyBox('No open questions.', IS_OWNER ? ASSISTANT_NAME + ' has not asked anything.' : 'Ask one from any task.')}
  </div>`;
}

function viewUrgent(){
  const list = found().filter(t => t.urgent && t.state !== 'done');
  return `<div class="panel">
    <div class="phead"><h3>Urgent</h3></div>
    ${list.length ? `<div class="rows">${list.map(t => taskRow(t, false)).join('')}</div>`
      : emptyBox('Nothing marked urgent.', 'Mark a task urgent and it shows up here.')}
  </div>`;
}

function profStats(code){
  const mine = tasks.filter(t => t.code === code);
  return {
    total: mine.length,
    open: mine.filter(t => t.state !== 'done').length,
    ask: mine.filter(t => t.state === 'ask').length,
    done: mine.filter(t => t.state === 'done').length,
    last: mine.length ? mine.map(t => t.updated).sort().pop() : null
  };
}

function viewProfiles(unnamedOnly){
  let list = accounts.filter(profHit);
  if (unnamedOnly) list = list.filter(a => !a.name);
  const order = {active:1, quiet:2};
  list = list.slice().sort((x, y) => {
    const sx = profStats(x.code), sy = profStats(y.code);
    if ((sy.open > 0) - (sx.open > 0)) return (sy.open > 0) - (sx.open > 0);
    if (sy.open !== sx.open) return sy.open - sx.open;
    const kx = codeKey(x.code), ky = codeKey(y.code);
    return kx[0] === ky[0] ? kx[1] - ky[1] : kx[0].localeCompare(ky[0]);
  });

  const rows = list.map(a => {
    const s = profStats(a.code);
    return `<div class="row" data-prof="${esc(a.code)}">
      <div class="rmain">
        <div class="rtop">
          <span class="pcode">${esc(a.code)}</span>
          <span class="pname ${a.name?'':'none'}">${a.name ? esc(a.name) : 'no name yet'}</span>
        </div>
        <div class="rsub">${s.total} task${s.total===1?'':'s'}<span class="sep">·</span>
          ${s.open} open<span class="sep">·</span>${s.done} done</div>
      </div>
      <div style="display:flex;align-items:center;gap:9px">
        ${s.ask ? '<span class="pill p-ask">' + s.ask + ' question' + (s.ask>1?'s':'') + '</span>' : ''}
        ${s.open ? '<span class="pill p-pending">' + s.open + ' open</span>'
                 : '<span class="pill p-flat">clear</span>'}
        <span class="rtime">${s.last ? when(s.last) : '—'}</span>
      </div>
      ${IS_OWNER ? `<div class="rbtns">
        <button class="iconb" data-edit="${esc(a.code)}" title="Edit artist name">✎</button>
        <button class="iconb" data-newfor="${esc(a.code)}" title="New task for this profile">+</button>
      </div>` : '<span class="chev">›</span>'}
    </div>`;
  }).join('');

  return `<div class="panel">
    <div class="phead">
      <h3>${unnamedOnly ? 'Unnamed profiles' : 'Profiles'}
        <span class="sub">${list.length} of ${accounts.length}</span></h3>
      ${IS_OWNER ? '<button class="seg" id="addprof-inline">+ Add profile</button>' : ''}
    </div>
    ${list.length ? `<div class="rows">${rows}</div>`
      : emptyBox('No profiles match.', 'Clear the search or add a new profile.')}
  </div>`;
}

function viewProfile(code){
  const a = accOf(code) || {code, name:''};
  const s = profStats(code);
  const mine = tasks.filter(t => t.code === code && hit(t));
  const open = mine.filter(t => t.state !== 'done');
  const done = mine.filter(t => t.state === 'done');

  return `<button class="back" id="back-btn">← All profiles</button>
  <div class="panel">
    <div class="phero">
      <div>
        <div class="big">${esc(code)}</div>
        <div class="nm ${a.name?'':'none'}">${a.name ? esc(a.name) : 'no name yet'}</div>
        <div class="mini">
          <div><b>${s.total}</b>tasks</div>
          <div><b>${s.open}</b>open</div>
          <div><b>${s.done}</b>done</div>
        </div>
      </div>
      <div class="dacts">
        ${IS_OWNER ? `<button class="ba" data-edit="${esc(code)}">${a.name ? 'Edit name' : 'Add name'}</button>` : ''}
        ${IS_OWNER ? `<button class="ba" data-newfor="${esc(code)}">+ New task</button>` : ''}
        ${IS_OWNER && s.total === 0 ? `<button class="ba dim" data-delprof="${esc(code)}">Delete profile</button>` : ''}
      </div>
    </div>
    ${open.length ? `<div class="phead" style="border-top:1px solid var(--line)">
        <h3>Open <span class="sub">${open.length}</span></h3></div>
      <div class="rows">${open.map(t => taskRow(t, true)).join('')}</div>` : ''}
    ${done.length ? `<div class="phead" style="border-top:1px solid var(--line)">
        <h3>Completed <span class="sub">${done.length}</span></h3></div>
      <div class="rows">${done.map(t => taskRow(t, true)).join('')}</div>` : ''}
    ${!mine.length ? emptyBox('No tasks on this profile yet.',
        IS_OWNER ? 'Send the first one with + New task above.' : 'Nothing assigned here.') : ''}
  </div>`;
}

function emptyBox(a, b){
  return `<div class="empty"><b>${esc(a)}</b>${esc(b)}</div>`;
}

/* ================= render + wiring ================= */
function render(){
  paintChrome();
  let html;
  if (openProfile) html = viewProfile(openProfile);
  else if (nav === 'home')     html = viewHome();
  else if (nav === 'profiles') html = viewProfiles(false);
  else if (nav === 'noname')   html = viewProfiles(true);
  else if (nav === 'done')     html = viewDone();
  else if (nav === 'questions')html = viewQuestions();
  else if (nav === 'urgent')   html = viewUrgent();
  else html = viewTasks();
  $('view').innerHTML = html;
  wire();
}

function wire(){
  const v = $('view');

  v.querySelectorAll('[data-sub]').forEach(b => b.onclick = () => { sub = b.dataset.sub; render(); });

  v.querySelectorAll('[data-prof]').forEach(r => r.onclick = e => {
    if (e.target.closest('.iconb')) return;
    openProfile = r.dataset.prof; expanded = {}; window.scrollTo(0,0); render();
  });

  v.querySelectorAll('[data-jump]').forEach(b => b.onclick = e => {
    e.stopPropagation();
    nav = b.dataset.jump; openProfile = null; expanded = {};
    if (nav === 'tasks') sub = 'all';
    window.scrollTo(0,0); render();
  });

  v.querySelectorAll('[data-goto]').forEach(r => r.onclick = e => {
    if (e.target.closest('button.ba')) return;
    const t = tasks.find(x => x.id == r.dataset.goto);
    if (!t) return;
    openProfile = null; nav = 'tasks'; sub = 'all';
    expanded = {}; expanded[t.id] = true;
    window.scrollTo(0,0); render();
  });

  v.querySelectorAll('[data-edit]').forEach(b => b.onclick = e => {
    e.stopPropagation();
    if (IS_OWNER && window.openProfileSheet) window.openProfileSheet(b.dataset.edit);
  });
  const back = $('back-btn');
  if (back) back.onclick = () => { openProfile = null; render(); };

  v.querySelectorAll('[data-task]').forEach(r => r.onclick = e => {
    if (e.target.closest('.detail') || e.target.closest('a') || e.target.closest('button.ba')) return;
    const id = r.dataset.task;
    expanded[id] = !expanded[id];
    render();
  });

  v.querySelectorAll('[data-done]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const t = tasks.find(x => x.id == b.dataset.done);
    b.disabled = true; b.textContent = 'Saving…';
    const r = await api('act', {id:+b.dataset.done, do:'done'});
    if (r.ok){ await refresh(); toast(t.code + ' marked done'); }
    else { b.disabled = false; b.textContent = 'Mark done'; toast(r.error || 'Could not save'); }
  });
  v.querySelectorAll('[data-ask]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const t = tasks.find(x => x.id == b.dataset.ask);
    const q = prompt('What do you want to ask about ' + t.code + '?');
    if (!q) return;
    const r = await api('act', {id:+b.dataset.ask, do:'ask', text:q});
    if (r.ok){ await refresh(); toast('Question sent'); } else toast(r.error || 'Could not send');
  });
  v.querySelectorAll('[data-answer]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const t = tasks.find(x => x.id == b.dataset.answer);
    const ans = prompt('Answer about ' + t.code + ':\n\n' + t.ask);
    if (!ans) return;
    const r = await api('act', {id:+b.dataset.answer, do:'answer', text:ans});
    if (r.ok){ await refresh(); toast('Sent back to ' + ASSISTANT_NAME); } else toast(r.error || 'Could not send');
  });
  v.querySelectorAll('[data-reopen]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    await api('act', {id:+b.dataset.reopen, do:'reopen'}); await refresh(); toast('Reopened');
  });
  v.querySelectorAll('[data-urg]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    await api('act', {id:+b.dataset.urg, do:'urgent'}); await refresh();
  });
  v.querySelectorAll('[data-del]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const t = tasks.find(x => x.id == b.dataset.del);
    if (!confirm('Delete this task on ' + t.code + '? This cannot be undone.')) return;
    await api('delete', {id:+b.dataset.del}); await refresh(); toast('Task deleted');
  });
  v.querySelectorAll('[data-copy]').forEach(b => b.onclick = e => {
    e.stopPropagation();
    const t = tasks.find(x => x.id == b.dataset.copy);
    const a = accOf(t.code) || {};
    const txt = [t.code + (a.name ? ' ' + a.name : ''), t.note, t.store, t.version]
      .filter(Boolean).concat(t.links || []).join('\n');
    navigator.clipboard.writeText(txt).then(() => toast('Copied'), () => toast('Copy blocked'));
  });

  v.querySelectorAll('[data-delprof]').forEach(b => b.onclick = async () => {
    const code = b.dataset.delprof;
    if (!confirm('Delete profile ' + code + '?')) return;
    const r = await api('delprofile', {code});
    if (r.ok){ openProfile = null; await refresh(); toast(code + ' deleted'); }
    else toast(r.error || 'Could not delete');
  });
  const inl = $('addprof-inline');
  if (inl && IS_OWNER) inl.onclick = () => $('addprof-btn').click();
  v.querySelectorAll('[data-newfor]').forEach(b => b.onclick = e => {
    e.stopPropagation();
    if (!IS_OWNER) return;
    $('new-btn').click();
    setTimeout(() => { $('f-acc').value = b.dataset.newfor; pick(b.dataset.newfor); }, 60);
  });
}

document.querySelectorAll('.nav button, .vb').forEach(b => b.onclick = () => {
  nav = b.dataset.nav; openProfile = null; expanded = {};
  if (nav === 'tasks') sub = 'all';
  window.scrollTo(0,0); render();
});

$('search').oninput = e => { query = e.target.value; render(); };
$('sclear').onclick = () => { query = ''; $('search').value = ''; render(); $('search').focus(); };

/* ================= data ================= */
async function refresh(){
  let d;
  try { d = await (await fetch('?api=list', {method:'POST',
        headers:{'Content-Type':'application/json'}, body:'{}'})).json(); }
  catch(e){ return; }
  if (!d || !d.tasks){ location.reload(); return; }
  tasks = d.tasks; accounts = d.accounts; TOKEN = d.token;
  render();
  const now = tasks.filter(t => t.state !== 'done').length;
  if (lastOpen > -1 && now > lastOpen && document.hidden) notify(now - lastOpen);
  lastOpen = now;
}
function notify(n){
  try {
    if (window.Notification && Notification.permission === 'granted')
      new Notification(n + ' new task' + (n>1?'s':'') + ' on Task Desk');
  } catch(e){}
}
if (window.Notification && Notification.permission === 'default')
  document.addEventListener('click', function once(){
    Notification.requestPermission(); document.removeEventListener('click', once);
  });

setInterval(refresh, 15000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
refresh();
</script>

<?php if ($isOwner): ?>
<script>
/* ================= send task ================= */
const scrim = $('scrim'), fAcc = $('f-acc'), fLinks = $('f-links'), fNote = $('f-note'),
      drop = $('drop'), fFile = $('f-file');
let store = STORES[0], version = VERSIONS[0], urgent = false, staged = [], hi = -1,
    rec = null, chunks = [];

function paintOpts(){
  $('opt-store').innerHTML = STORES.map(s =>
    `<button class="opt" data-store="${esc(s)}" aria-pressed="${store===s}">${esc(s)}</button>`).join('');
  $('opt-ver').innerHTML = VERSIONS.map(x =>
    `<button class="opt" data-ver="${esc(x)}" aria-pressed="${version===x}">${esc(x)}</button>`).join('');
  document.querySelectorAll('[data-store]').forEach(b => b.onclick = () => { store = b.dataset.store; paintOpts(); });
  document.querySelectorAll('[data-ver]').forEach(b => b.onclick = () => { version = b.dataset.ver; paintOpts(); });
  $('urg-btn').setAttribute('aria-pressed', urgent);
}
$('urg-btn').onclick = () => { urgent = !urgent; paintOpts(); };

function matches(q){
  q = q.trim().toLowerCase();
  const list = accounts.slice().sort((x, y) => {
    const kx = codeKey(x.code), ky = codeKey(y.code);
    return kx[0] === ky[0] ? kx[1] - ky[1] : kx[0].localeCompare(ky[0]);
  });
  if (!q) return list.slice(0, 80);
  return list.filter(a => a.code.toLowerCase().indexOf(q) === 0
    || (a.name && a.name.toLowerCase().includes(q))).slice(0, 80);
}
function paintDrop(){
  const m = matches(fAcc.value);
  drop.innerHTML = m.length ? m.map((a,i) => {
    const n = tasks.filter(t => t.code === a.code).length;
    return `<button data-pick="${esc(a.code)}" class="${i===hi?'hi':''}">
      <span class="c">${esc(a.code)}</span>
      <span class="nm">${a.name ? esc(a.name) : '—'}</span>
      <span class="mk">${n || ''}</span></button>`;
  }).join('') : '<button disabled style="color:var(--soft);cursor:default">New code — the profile is created automatically</button>';
  drop.classList.add('open');
  drop.querySelectorAll('[data-pick]').forEach(b =>
    b.onmousedown = e => { e.preventDefault(); pick(b.dataset.pick); });
}
function pick(code){
  fAcc.value = code; drop.classList.remove('open'); hi = -1;
  const a = accOf(code);
  const past = tasks.filter(t => t.code === code).length;
  $('accname').textContent = a
    ? (a.name || 'No name yet') + (past ? ' · ' + past + ' past task' + (past>1?'s':'') : ' · first task')
    : 'New profile — it will be created';
  fLinks.focus();
}
fAcc.oninput = () => { hi = -1; paintDrop(); $('accname').textContent = 'Type a code or an artist name'; };
fAcc.onfocus = paintDrop;
fAcc.onblur = () => setTimeout(() => drop.classList.remove('open'), 160);
fAcc.onkeydown = e => {
  const m = matches(fAcc.value);
  if (e.key === 'ArrowDown'){ hi = Math.min(hi+1, m.length-1); paintDrop(); e.preventDefault(); }
  else if (e.key === 'ArrowUp'){ hi = Math.max(hi-1, 0); paintDrop(); e.preventDefault(); }
  else if (e.key === 'Enter' && m[hi]){ pick(m[hi].code); e.preventDefault(); }
};
fLinks.oninput = () => {
  const n = (fLinks.value.match(/https?:\/\/\S+/g) || []).length;
  $('linkcount').textContent = n ? n + ' link' + (n>1?'s':'') + ' read' : 'No links yet';
};

/* ---- attachments ---- */
function paintPicked(){
  $('picked').innerHTML = staged.map((f,i) =>
    `<li><span>${esc(f.name)}</span><button data-rm="${i}" aria-label="Remove">×</button></li>`).join('');
  $('picked').querySelectorAll('[data-rm]').forEach(b =>
    b.onclick = () => { staged.splice(+b.dataset.rm, 1); paintPicked(); });
}
$('pick-btn').onclick = () => fFile.click();
fFile.onchange = () => {
  for (const f of fFile.files) staged.push({name:f.name, blob:f});
  fFile.value = ''; paintPicked();
};
$('rec-btn').onclick = async () => {
  if (rec && rec.state === 'recording'){ rec.stop(); return; }
  if (!navigator.mediaDevices || !window.MediaRecorder){ toast('Recording not supported here'); return; }
  try {
    const stream = await navigator.mediaDevices.getUserMedia({audio:true});
    chunks = []; rec = new MediaRecorder(stream);
    rec.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
    rec.onstop = () => {
      stream.getTracks().forEach(t => t.stop());
      const type = chunks[0] ? chunks[0].type : 'audio/webm';
      const ext = type.indexOf('mp4') > -1 ? 'm4a' : type.indexOf('ogg') > -1 ? 'ogg' : 'webm';
      staged.push({name:'voice-' + new Date().toTimeString().slice(0,5).replace(':','') + '.' + ext,
                   blob:new Blob(chunks, {type})});
      paintPicked();
      $('rec-btn').textContent = 'Record voice note';
      $('rec-btn').classList.remove('rec');
      toast('Voice note added');
    };
    rec.start();
    $('rec-btn').textContent = '■ Stop recording';
    $('rec-btn').classList.add('rec');
  } catch(e){ toast('Microphone blocked'); }
};
async function uploadStaged(){
  if (!staged.length) return [];
  const fd = new FormData();
  fd.append('token', TOKEN);
  staged.forEach(f => fd.append('f[]', f.blob, f.name));
  try {
    const r = await (await fetch('?api=upload', {method:'POST', body:fd})).json();
    return r.ok ? r.files : [];
  } catch(e){ return []; }
}

/* ---- open / close / save ---- */
$('new-btn').onclick = () => {
  scrim.classList.add('open');
  fAcc.value = fLinks.value = fNote.value = '';
  store = STORES[0]; version = VERSIONS[0]; urgent = false; staged = []; hi = -1;
  paintOpts(); paintPicked();
  $('accname').textContent = 'Type a code or an artist name';
  $('linkcount').textContent = 'No links yet';
  setTimeout(() => fAcc.focus(), 40);
};
const closeSheet = () => scrim.classList.remove('open');
$('cancel-btn').onclick = closeSheet;
scrim.onclick = e => { if (e.target === scrim) closeSheet(); };

$('save-btn').onclick = async () => {
  const code = fAcc.value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (!code){ fAcc.focus(); toast('Pick a profile first'); return; }
  const btn = $('save-btn');
  btn.disabled = true; btn.textContent = staged.length ? 'Uploading…' : 'Sending…';
  const files = await uploadStaged();
  const r = await api('create', {
    code, links: (fLinks.value.match(/https?:\/\/\S+/g) || []).map(u => u.split('?si=')[0]),
    files, store, version, note: fNote.value.trim(), urgent
  });
  btn.disabled = false; btn.textContent = 'Send to ' + ASSISTANT_NAME;
  if (!r.ok){ toast(r.error || 'Could not save'); return; }
  closeSheet();
  if (!openProfile){ nav = 'tasks'; sub = 'all'; }
  await refresh(); toast(code + ' sent to ' + ASSISTANT_NAME);
};

/* ============ add / edit profile ============ */
const pscrim = $('pscrim');
let editing = null;

window.openProfileSheet = function(code){
  editing = code || null;
  const a = editing ? (accOf(editing) || {code:editing, name:''}) : null;
  $('np-h').textContent   = editing ? 'Edit profile' : 'Add profile';
  $('np-sub').textContent = editing
    ? 'Change the artist name for this profile. The code stays the same.'
    : 'A profile is one artist account. The code is how you refer to it.';
  $('np-code').value = editing ? a.code : '';
  $('np-code').readOnly = !!editing;
  $('np-code').style.opacity = editing ? '.55' : '1';
  $('np-name').value = editing ? (a.name || '') : '';
  $('np-save').textContent = editing ? 'Save name' : 'Add profile';
  pscrim.classList.add('open');
  setTimeout(() => (editing ? $('np-name') : $('np-code')).focus(), 40);
};

$('addprof-btn').onclick = () => window.openProfileSheet(null);
$('np-cancel').onclick = () => pscrim.classList.remove('open');
pscrim.onclick = e => { if (e.target === pscrim) pscrim.classList.remove('open'); };

$('np-save').onclick = async () => {
  const name = $('np-name').value.trim();
  if (editing){
    const r = await api('rename', {code: editing, name});
    if (!r.ok){ toast(r.error || 'Could not save'); return; }
    pscrim.classList.remove('open');
    await refresh(); toast('Name saved');
    return;
  }
  const code = $('np-code').value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (!code){ $('np-code').focus(); toast('Enter a code'); return; }
  const r = await api('addprofile', {code, name});
  if (!r.ok){ toast(r.error || 'Could not add'); return; }
  pscrim.classList.remove('open');
  nav = 'profiles'; openProfile = code;
  await refresh(); toast(code + ' added');
};

$('np-code').onkeydown = e => { if (e.key === 'Enter') $('np-name').focus(); };
$('np-name').onkeydown = e => { if (e.key === 'Enter') $('np-save').click(); };

document.addEventListener('keydown', e => {
  if (e.key === 'Escape'){ closeSheet(); pscrim.classList.remove('open'); }
  if (e.key === 'n' && e.target === document.body){ e.preventDefault(); $('new-btn').click(); }
  if (e.key === '/' && e.target === document.body){ e.preventDefault(); $('search').focus(); }
});
paintOpts();
</script>
<?php endif; ?>

</body>
</html>
