<?php
// ==================================================================
// HTPWatchProducts - Configuration fournisseurs
// Dolibarr 23.0.2 - NAS Synology DS418
// ==================================================================
// Version: 20260605 Build: 2600
// Fichier: __FILE__ (portable)
// ==================================================================
require_once __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$PATHFILE = __FILE__;
$VERSION  = '20260605';
$BUILD    = '2600';
$DEBUG_LIGHT  = true;
$DEBUG_ERRORS = false; // ✅ false pour moins de verbosité

if ($DEBUG_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

llxHeader('', 'HTP Watch Products Setup');
global $db, $conf;

if ($DEBUG_LIGHT) {
    print '<div style="background:#e7f3ff;padding:8px;margin:10px;border-left:4px solid #007bff;font-family:monospace;font-size:11px;">';
    print '<strong>🔍 HTPWatchProducts</strong> | Version '.$VERSION.' | Build '.$BUILD.' | '.htmlspecialchars(basename($PATHFILE));
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
    
    // ✅ INGRAM MICRO - Champs spécifiques
    if ($type == 'ingrammicro') {
        dolibarr_set_const($db, 'HTP_INGRAMMICRO_CLIENT_ID', $_POST['client_id'] ?? '', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'HTP_INGRAMMICRO_CLIENT_SECRET', $_POST['client_secret'] ?? '', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'HTP_INGRAMMICRO_CUSTOMER_NUMBER', $_POST['customer_number'] ?? '', 'chaine', 0, '', $conf->entity);
    }
    // ✅ AUTRES FOURNISSEURS - Champs classiques
    else {
        dolibarr_set_const($db, 'HTP_'.$type.'_URL', $_POST['url'], 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'HTP_'.$type.'_LOGIN', $_POST['login'], 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'HTP_'.$type.'_PASSWORD', $_POST['password'], 'chaine', 0, '', $conf->entity);
    }
    
    $test_result = '<div style="color:green;margin:10px;"><b>Sauvegarde OK ✅</b></div>';
}

/* =========================
   TEST
   ========================= */
if ($action == 'test') {
    $type  = $_POST['type'];
    $test_result = '<div style="margin:10px;padding:10px;background:#eee;">';
    $test_result .= '<b>Test fournisseur : '.strtoupper($type).'</b><br>';
    
    // ✅ INGRAM MICRO - TEST GÉNÉRIQUE (OAuth2 uniquement)
    if ($type == 'ingrammicro') {
        $client_id = $_POST['client_id'] ?? '';
        $client_secret = $_POST['client_secret'] ?? '';
        $customer_number = $_POST['customer_number'] ?? '';
        
        if (!$client_id || !$client_secret || !$customer_number) {
            $test_result .= '<span style="color:red;">❌ Champs manquants (Client ID, Secret, Customer Number)</span>';
        } else {
            // Appel API OAuth2 - Token uniquement (méthode générique)
            $ch = curl_init('https://api.ingrammicro.com/oauth/oauth20/token');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials&client_id=$client_id&client_secret=$client_secret");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $token_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $token_data = json_decode($token_response, true);
            $access_token = $token_data['access_token'] ?? null;
            $expires_in = $token_data['expires_in'] ?? 0;
            
            if ($access_token && $access_token !== 'null') {
                $test_result .= '<span style="color:green;">✅ Connexion API Ingram Micro OK</span><br>';
                $test_result .= '<span style="font-size:11px;color:#666;">';
                $test_result .= 'Token: '.substr($access_token, 0, 10).'... | ';
                $test_result .= 'Expire dans: '.($expires_in / 3600).'h';
                $test_result .= '</span>';
            } else {
                $test_result .= '<span style="color:red;">❌ Échec obtention token OAuth2</span>';
                if ($DEBUG_ERRORS) {
                    $test_result .= '<div style="background:#f8d7da;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
                    $test_result .= '<b>Erreur :</b> HTTP '.$http_code.'<br>';
                    $test_result .= '<b>Réponse :</b><br>'.htmlspecialchars($token_response);
                    $test_result .= '</div>';
                }
            }
        }
    }
    // ✅ ESPACEPC - TEST SPÉCIFIQUE
    elseif ($type == 'espacepc') {
        $url = $_POST['url'];
        $login = $_POST['login'];
        $pass = $_POST['password'];
        
        if (!$url || !$login || !$pass) {
            $test_result .= '<span style="color:red;">Champs manquants</span>';
        } else {
            if ($DEBUG_ERRORS) {
                $test_result .= '<div style="background:#fff;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
                $test_result .= '<b>🔍 Debug EspacePC :</b><br>';
                $test_result .= 'URL: '.htmlspecialchars($url).'<br>';
                $test_result .= 'Login: '.htmlspecialchars($login).'<br>';
                $test_result .= 'Méthode: curl POST /identification<br>';
                $test_result .= '</div>';
            }
            
            $cookie = tempnam(sys_get_temp_dir(), 'ck_');
            
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
    }
    // ✅ ASIALAND/ACADIA - TEST ORIGINAL
    else {
        $url = $_POST['url'];
        $login = $_POST['login'];
        $pass = $_POST['password'];
        
        if (!$url || !$login || !$pass) {
            $test_result .= '<span style="color:red;">Champs manquants</span>';
        } else {
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
   DISPLAY ROW - INGRAM MICRO
   ========================= */
function display_row_ingram($name, $type) {
    global $conf;
    $client_id = $conf->global->{'HTP_'.$type.'_CLIENT_ID'} ?? '';
    $client_secret = $conf->global->{'HTP_'.$type.'_CLIENT_SECRET'} ?? '';
    $customer_number = $conf->global->{'HTP_'.$type.'_CUSTOMER_NUMBER'} ?? '';
    
    print '<form method="post">';
    print '<input type="hidden" name="token" value="'.(function_exists('newToken') ? newToken() : '').'">';
    print '<input type="hidden" name="type" value="'.$type.'">';
    print '<tr>';
    print '<td><b>'.$name.'</b></td>';
    print '<td colspan="3">';
    print '<table style="width:100%;border:none;">';
    print '<tr>';
    print '<td style="width:33%;border:none;padding:2px;"><label style="font-size:10px;color:#666;">Client ID</label><br>';
    print '<input type="text" name="client_id" value="'.$client_id.'" size="30"></td>';
    print '<td style="width:33%;border:none;padding:2px;"><label style="font-size:10px;color:#666;">Client Secret</label><br>';
    print '<input type="password" name="client_secret" value="'.$client_secret.'" size="30"></td>';
    print '<td style="width:33%;border:none;padding:2px;"><label style="font-size:10px;color:#666;">Customer Number</label><br>';
    print '<input type="text" name="customer_number" value="'.$customer_number.'" size="20" placeholder="21-504598"></td>';
    print '</tr></table>';
    print '</td>';
    print '<td><span style="background:#ff6b35;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">API REST</span></td>';
    print '<td>';
    print '<input type="submit" name="action" value="test">';
    print '<input type="submit" name="action" value="save">';
    print '</td>';
    print '</tr>';
    print '</form>';
}

/* =========================
   DISPLAY ROW - CLASSIQUE
   ========================= */
function display_row($name, $type) {
    global $conf;
    $url = $conf->global->{'HTP_'.$type.'_URL'} ?? '';
    $login = $conf->global->{'HTP_'.$type.'_LOGIN'} ?? '';
    $password = $conf->global->{'HTP_'.$type.'_PASSWORD'} ?? '';
    
    print '<form method="post">';
    print '<input type="hidden" name="token" value="'.(function_exists('newToken') ? newToken() : '').'">';
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
display_row('EspacePC', 'espacepc');

print '<tr class="liste_titre" style="background:#fff3e0;">';
print '<th colspan="6">🔗 Fournisseur API REST</th>';
print '</tr>';
display_row_ingram('Ingram Micro', 'ingrammicro');

print '</table>';

print '<div style="background:#e8f5e9;padding:10px;margin:10px 0;border-left:4px solid #4caf50;font-size:12px;">';
print '<b>ℹ️ Info Ingram Micro :</b><br>';
print 'Ce fournisseur utilise une <b>API REST OAuth2</b> (pas de scraping).<br>';
print 'URL produit type : <code>https://fr-new.ingrammicro.com/cep/app/product/productdetails?id=<b>CN41188</b></code><br>';
print 'Le <b>Ingram Part Number</b> (PN) est extrait automatiquement de l\'URL via la regex : <code>/[?&]id=([A-Z0-9-]+)/i</code><br>';
print '<span style="color:#666;font-size:11px;">Si Ingram change son format d\'URL, il faudra modifier cette regex dans le code.</span>';
print '</div>';

llxFooter();