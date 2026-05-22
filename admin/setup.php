<?php
// ==================================================================
// HTPWatchProducts - Configuration fournisseurs
// Dolibarr 23.0.2 - NAS Synology DS418
// ==================================================================
// Version: 20260522 Build: 2500
// Fichier: /volume1/web/dolibarr_test/htdocs/custom/htpwatchproducts/admin/setup.php
// ==================================================================

require_once __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$PATHFILE = '/volume1/web/dolibarr_test/htdocs/custom/htpwatchproducts/admin/setup.php';
$VERSION  = '20260522';
$BUILD    = '1710';
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
    print ' | <a href="'.DOL_URL_ROOT.'/admin/modules.php">Retour aux modules</a>';
    print '</div>';
}

$action = $_POST['action'] ?? '';
$test_result = null;

/* =========================
SAVE
========================= */
if ($action == 'save') {
    $type = $_POST['type'];
    dolibarr_set_const($db, 'HTP_'.$type.'_URL', $_POST['url'], 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'HTP_'.$type.'_LOGIN', $_POST['login'], 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'HTP_'.$type.'_PASSWORD', $_POST['password'], 'chaine', 0, '', $conf->entity);
    $test_result = '<div style="color:green;margin:10px;"><b>Sauvegarde OK ✅</b></div>';
}

/* =========================
TEST
========================= */
if ($action == 'test') {
    $type  = $_POST['type'];
    $url   = $_POST['url'];
    $login = $_POST['login'];
    $pass  = $_POST['password'];
    
    $test_result = '<div style="margin:10px;padding:10px;background:#eee;">';
    $test_result .= '<b>Test fournisseur : '.strtoupper($type).'</b><br>';
    
    if (!$url || !$login || !$pass) {
        $test_result .= '<span style="color:red;">Champs manquants</span>';
    } else {
        // ✅ ESPACEPC - TEST SPÉCIFIQUE
        if ($type == 'espacepc') {
            if ($DEBUG_ERRORS) {
                $test_result .= '<div style="background:#fff;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
                $test_result .= '<b>🔍 Debug EspacePC :</b><br>';
                $test_result .= 'URL: '.htmlspecialchars($url).'<br>';
                $test_result .= 'Login: '.htmlspecialchars($login).'<br>';
                $test_result .= 'Méthode: curl POST /identification<br>';
                $test_result .= '</div>';
            }
            
            $cookie = tempnam(sys_get_temp_dir(), 'ck_');
            
            // Login via /identification
            $ch = curl_init('https://www.espacepc.com/identification');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "in_login=$login&in_password=$pass&action=identification");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_REFERER, 'https://www.espacepc.com/');
            $login_result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Test session sur homepage
            $ch = curl_init('https://www.espacepc.com/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $res = curl_exec($ch);
            curl_close($ch);
            @unlink($cookie);
            
            if (stripos($res, 'déconnexion') !== false || stripos($res, 'mon compte') !== false) {
                $test_result .= '<span style="color:green;">Connexion OK ✅</span>';
            } else {
                $test_result .= '<span style="color:red;">Connexion KO ❌</span>';
            }
            
            if ($DEBUG_ERRORS) {
                $test_result .= '<div style="background:#fff;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
                $test_result .= '<b>📊 Réponse brute :</b><br>';
                $test_result .= 'HTTP Code login: '.$http_code.'<br>';
                $indicators = [];
                if (stripos($res, 'déconnexion') !== false) $indicators[] = 'Déconnexion';
                if (stripos($res, 'mon compte') !== false) $indicators[] = 'Mon compte';
                $test_result .= 'Indicateurs trouvés: '.(empty($indicators) ? 'AUCUN' : implode(', ', $indicators)).'<br>';
                $test_result .= 'Longueur réponse: '.strlen($res).' caractères<br>';
                if (strlen($res) < 500) {
                    $test_result .= '<b>Contenu complet :</b><br>'.htmlspecialchars($res);
                } else {
                    $test_result .= '<b>Extrait (500 premiers caractères) :</b><br>'.htmlspecialchars(substr($res, 0, 500));
                }
                $test_result .= '</div>';
            }
        }
        // ✅ ASIALAND/ACADIA - TEST ORIGINAL (INTACT)
        else {
            $html = @file_get_contents($url);
            preg_match('/"token":"([^"]+)"/', $html, $m);
            $token = $m[1] ?? '';
            
            if ($DEBUG_ERRORS) {
                $test_result .= '<div style="background:#fff;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
                $test_result .= '<b>🔍 Debug Prestashop :</b><br>';
                $test_result .= 'URL: '.htmlspecialchars($url).'<br>';
                $test_result .= 'Login: '.htmlspecialchars($login).'<br>';
                $test_result .= 'Token trouvé: '.($token ? 'OUI ('.substr($token, 0, 20).'...)' : 'NON').'<br>';
                $test_result .= '</div>';
            }
            
            if (!$token) {
                $test_result .= '<span style="color:red;">Token non trouvé</span>';
                if ($DEBUG_ERRORS) {
                    $test_result .= '<div style="background:#f8d7da;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
                    $test_result .= '<b>HTML récupéré (200 premiers caractères) :</b><br>';
                    $test_result .= htmlspecialchars(substr($html, 0, 200));
                    $test_result .= '</div>';
                }
            } else {
                $cookie = tempnam(sys_get_temp_dir(), 'ck_');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "email=$login&password=$pass&submitLogin=1&back=my-account&token=$token");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
                curl_exec($ch);
                curl_close($ch);
                
                $test_url = ($type == 'asialand') ? "https://www.asialand.fr/" : "https://www.acadia-info.com/";
                $ch = curl_init($test_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
                $res = curl_exec($ch);
                curl_close($ch);
                @unlink($cookie);
                
                if (strpos($res, '"is_logged":true') !== false) {
                    $test_result .= '<span style="color:green;">Connexion OK ✅</span>';
                } else {
                    $test_result .= '<span style="color:red;">Connexion KO ❌</span>';
                }
                
                if ($DEBUG_ERRORS) {
                    $test_result .= '<div style="background:#fff;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
                    $test_result .= '<b>📊 Réponse brute :</b><br>';
                    $test_result .= 'is_logged present: '.(strpos($res, '"is_logged"') !== false ? 'OUI' : 'NON').'<br>';
                    $test_result .= 'Longueur réponse: '.strlen($res).' caractères<br>';
                    if (strlen($res) < 500) {
                        $test_result .= '<b>Contenu complet :</b><br>'.htmlspecialchars($res);
                    } else {
                        $test_result .= '<b>Extrait (500 premiers caractères) :</b><br>'.htmlspecialchars(substr($res, 0, 500));
                    }
                    $test_result .= '</div>';
                }
            }
        }
    }
    $test_result .= '</div>';
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

// Affichage résultat test/save (si existe)
if ($test_result) {
    print $test_result;
}

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
display_row('EspacePC', 'espacepc');  // ✅ NOUVEAU

print '</table>';
llxFooter();