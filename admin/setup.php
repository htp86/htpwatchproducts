<?php

require_once __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$PATHFILE = '/volume1/web/dolibarr_test/htdocs/custom/htpwatchproducts/admin/setup.php';
$VERSION  = '20260520';
$BUILD    = '1807';
$DEBUG_LIGHT  = true;
$DEBUG_ERRORS = true;

if ($DEBUG_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

llxHeader('', 'HTP Watch Products Setup');

global $db, $conf;

if ($DEBUG_LIGHT) {
    print '<div style="background:#e7f3ff;padding:8px;margin:10px;border-left:4px solid #007bff;font-family:monospace;font-size:11px;">';
    print '<strong>🔍 HTPWatchProducts</strong> | Version '.$VERSION.' | Build '.$BUILD.' | '.htmlspecialchars($PATHFILE);
    print '</div>';
}

/* =========================
   SECURITE POST
========================= */
$action = $_POST['action'] ?? '';

/* =========================
   SAVE
========================= */
if ($action == 'save') {

    $type = $_POST['type'];

    dolibarr_set_const($db, 'HTP_'.$type.'_URL', $_POST['url'], 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'HTP_'.$type.'_LOGIN', $_POST['login'], 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'HTP_'.$type.'_PASSWORD', $_POST['password'], 'chaine', 0, '', $conf->entity);

    print '<div style="color:green;margin:10px;"><b>Sauvegarde OK ✅</b></div>';
}

/* =========================
   TEST
========================= */
if ($action == 'test') {

    $type  = $_POST['type'];
    $url   = $_POST['url'];
    $login = $_POST['login'];
    $pass  = $_POST['password'];

    print '<div style="margin:10px;padding:10px;background:#eee;">';
    print '<b>Test fournisseur :</b><br>';

    if (!$url || !$login || !$pass) {

        print '<span style="color:red;">Champs manquants</span>';

    } else {

        $html = @file_get_contents($url);

        preg_match('/"token":"([^"]+)"/', $html, $m);
        $token = $m[1] ?? '';

        if (!$token) {

            print '<span style="color:red;">Token non trouvé</span>';

        } else {

            $cookie = tempnam(sys_get_temp_dir(), 'ck_');

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,
                "email=$login&password=$pass&submitLogin=1&back=my-account&token=$token"
            );

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);

            curl_exec($ch);
            curl_close($ch);

            // test session
            if ($type == 'asialand') {
                $test_url = "https://www.asialand.fr/";
            } else {
                $test_url = "https://www.acadia-info.com/";
            }

            $ch = curl_init($test_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);

            $res = curl_exec($ch);
            curl_close($ch);

            if (strpos($res, '"is_logged":true') !== false) {
                print '<span style="color:green;">Connexion OK ✅</span>';
            } else {
                print '<span style="color:red;">Connexion KO ❌</span>';
            }
        }
    }

    print '</div>';
}

/* =========================
   DISPLAY ROW
========================= */
function display_row($name, $type) {

    global $conf;

    $url = $conf->global->{'HTP_'.$type.'_URL'} ?? '';
    $login = $conf->global->{'HTP_'.$type.'_LOGIN'} ?? '';
    $password = $conf->global->{'HTP_'.$type.'_PASSWORD'} ?? '';

    print '<form method="post">';
    print '<input type="hidden" name="token" value="'.newToken().'">';

    print '<tr>';

    print '<td>'.$name.'</td>';
    print '<td><input type="text" name="url" value="'.$url.'" size="40"></td>';
    print '<td><input type="text" name="login" value="'.$login.'"></td>';
    print '<td><input type="password" name="password" value="'.$password.'"></td>';
    print '<td>'.htmlspecialchars($type).'</td>';

    print '<td>';
    print '<input type="hidden" name="type" value="'.$type.'">';
    print '<input type="submit" name="action" value="test">';
    print '<input type="submit" name="action" value="save">';
    print '</td>';

    print '</tr>';
    print '</form>';
}

/* =========================
   TABLE
========================= */

print '<h2>Configuration Fournisseurs</h2>';

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<th>Nom</th>';
print '<th>URL Connexion</th>';
print '<th>Login</th>';
print '<th>Password</th>';
print '<th>Type</th>';
print '<th>Actions</th>';
print '</tr>';

display_row('Asialand', 'asialand');
display_row('Acadia', 'acadia');

print '</table>';

llxFooter();
