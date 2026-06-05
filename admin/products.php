<?php
// ==================================================================
// HTPWatchProducts - Page de gestion des produits surveillés
// Dolibarr 23.0.2 - NAS Synology DS418
// ==================================================================
// Version: 20260605 Build: 2600
// Fichier: /volume1/web/dolibarr_test/htdocs/custom/htpwatchproducts/admin/products.php
// ==================================================================
$PATHFILE = __FILE__;
$VERSION  = '20260605';
$BUILD    = '1620';
$DEBUG_LIGHT  = true;
$DEBUG_ERRORS = false;
$DEBUG_DEV    = false;

if ($DEBUG_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// =================================================================
// CHARGEMENT DOLIBARR
// =================================================================
require_once __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (file_exists(__DIR__.'/../lib/htpwatchproducts.lib.php')) {
    require_once __DIR__.'/../lib/htpwatchproducts.lib.php';
}

global $db, $conf, $user, $langs;

// =================================================================
// 🔍 DEBUG HEADER
// =================================================================
if ($DEBUG_LIGHT) {
    print '<div style="position:fixed;top:0;left:0;width:100%;z-index:9999;background:#e7f3ff;padding:6px;border-bottom:2px solid #007bff;font-family:monospace;font-size:11px;color:#000;">';
    print '🔍 HTPWatchProducts';
    print ' | Version '.$VERSION;
    print ' | Build '.$BUILD;
    print ' | '.htmlspecialchars(basename($PATHFILE));
    print ' | User: '.(isset($user) && isset($user->login) ? $user->login : 'NO_USER');
    print '</div><div style="height:30px;"></div>';
}

llxHeader('', 'HTP Watch Products - Produits surveillés');

// =================================================================
// 🎯 GESTION DES ACTIONS POST
// =================================================================
$action = GETPOST('action', 'alpha');
$token  = GETPOST('token', 'alpha');
$message = '';
$message_type = '';
$test_price = null;
$test_result = null;
$csrf_ok = (!empty($token) && $token === ($_SESSION['newtoken'] ?? ''));

// Récupérer message de session (après redirection PRG)
if (isset($_SESSION['htp_message'])) {
    $message = $_SESSION['htp_message'];
    $message_type = $_SESSION['htp_message_type'] ?? 'success';
    unset($_SESSION['htp_message']);
    unset($_SESSION['htp_message_type']);
}

// =================================================================
// 📊 FONCTION : CALCUL VARIATION PRIX
// =================================================================
function get_price_variation($price_history_json) {
    if (empty($price_history_json)) {
        return ['icon' => '–', 'color' => '#999', 'text' => 'Nouveau'];
    }
    
    $history = json_decode($price_history_json, true);
    if (!is_array($history) || count($history) < 2) {
        return ['icon' => '=', 'color' => '#999', 'text' => 'Premier prix'];
    }
    
    $last_price = $history[count($history) - 1]['price'];
    $prev_price = $history[count($history) - 2]['price'];
    
    if ($last_price > $prev_price) {
        return ['icon' => '⬆️', 'color' => '#dc3545', 'text' => 'Hausse +'.number_format($last_price - $prev_price, 2).' €'];
    } elseif ($last_price < $prev_price) {
        return ['icon' => '⬇️', 'color' => '#28a745', 'text' => 'Baisse -'.number_format($prev_price - $last_price, 2).' €'];
    } else {
        return ['icon' => '=', 'color' => '#6c757d', 'text' => 'Stable'];
    }
}

// =================================================================
// ➕ AJOUTER UN PRODUIT
// =================================================================
if ($action == 'add_product' && $csrf_ok) {
    $label    = trim(GETPOST('label', 'alpha'));
    $url      = trim(GETPOST('url', 'alpha'));
    $supplier = strtolower(trim(GETPOST('supplier', 'alpha')));
    
    $rowid = htpwatchproducts_add_product($db, $label, $url, $supplier, $user);
    
    if ($rowid > 0) {
        $result = htpwatchproducts_refresh_price($db, $rowid, false);
        if ($result['success']) {
            $_SESSION['htp_message'] = '✅ Produit "'.$label.'" ajouté avec succès (ID: '.$rowid.') - Prix: '.$result['price'].' €';
        } else {
            $_SESSION['htp_message'] = '✅ Produit ajouté mais prix non récupéré : '.$result['message'];
        }
        $_SESSION['htp_message_type'] = 'success';
    } elseif ($rowid == -2) {
        $_SESSION['htp_message'] = '❌ URL invalide';
        $_SESSION['htp_message_type'] = 'error';
    } else {
        $_SESSION['htp_message'] = '❌ Erreur ajout (label/URL/supplier vides ou SQL)';
        $_SESSION['htp_message_type'] = 'error';
    }
    
    // Pattern PRG : Redirection après POST
    header('Location: '.$_SERVER['PHP_SELF']);
}

// 🔄 ACTUALISER UN PRIX
if ($action == 'refresh_price' && $csrf_ok) {
    $rowid = GETPOST('rowid', 'int');
    $result = htpwatchproducts_refresh_price($db, $rowid, $DEBUG_ERRORS);
    
    if ($result['success']) {
        $_SESSION['htp_message'] = '✅ Prix actualisé : '.htpwatchproducts_format_price($result['price']);
        $_SESSION['htp_message_type'] = 'success';
    } else {
        $_SESSION['htp_message'] = '❌ '.$result['message'];
        $_SESSION['htp_message_type'] = 'error';
    }
    
    // Pattern PRG : Redirection après POST
    header('Location: '.$_SERVER['PHP_SELF']);
}

// 🔄 ACTUALISER TOUS LES PRIX (avec progression)
if ($action == 'refresh_all') {
    if (!$csrf_ok) {
        $_SESSION['htp_message'] = '❌ Erreur de token CSRF';
        $_SESSION['htp_message_type'] = 'error';
        header('Location: '.$_SERVER['PHP_SELF']);
        // exit; supprimé pour PRG
    }
    
    $products = htpwatchproducts_list_products($db);
    $total = count($products);
    $count_success = 0;
    $errors = 0;
    
    // Affichage progression
    print '<div style="background:#e7f3ff;padding:20px;margin:10px 0;border-radius:5px;">';
    print '<h3>🔄 Actualisation en cours...</h3>';
    print '<div style="background:#fff;border:2px solid #007bff;border-radius:5px;height:40px;position:relative;margin:15px 0;overflow:hidden;">';
    print '<div id="progress-bar" style="background:linear-gradient(90deg, #007bff 0%, #0056b3 100%);height:100%;width:0%;transition:width 0.3s;"></div>';
    print '<div id="progress-text" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:bold;color:#000;font-size:16px;">0/'.$total.'</div>';
    print '</div>';
    print '<div id="progress-details" style="font-size:13px;color:#333;max-height:300px;overflow-y:auto;">';
    print '</div>';
    print '</div>';
    
    // Script JavaScript pour mise à jour progression
    print '<script>
    var total = '.$total.';
    var current = 0;
    function updateProgress(productName, success, price) {
        current++;
        var percent = (current/total)*100;
        document.getElementById("progress-bar").style.width = percent+"%";
        document.getElementById("progress-text").textContent = current+"/"+total;
        var status = success ? "✅" : "❌";
        var priceText = success ? " - "+price+" €" : "";
        var div = document.createElement("div");
        div.style.padding = "5px";
        div.style.borderBottom = "1px solid #ddd";
        div.innerHTML = status+" <b>"+current+"/"+total+"</b> "+productName+priceText;
        document.getElementById("progress-details").appendChild(div);
        document.getElementById("progress-details").scrollTop = document.getElementById("progress-details").scrollHeight;
    }
    </script>';
    
    flush(); ob_flush();
    
    foreach ($products as $prod) {
        $result = htpwatchproducts_refresh_price($db, $prod->rowid, false);
        
        if ($result['success']) {
            $count_success++;
            $price = $result['price'];
            $success_str = 'true';
        } else {
            $errors++;
            $price = '0';
            $success_str = 'false';
        }
        
        // Mise à jour JavaScript
        print '<script>updateProgress("'.addslashes($prod->label).'", '.$success_str.', "'.$price.'");</script>';
        flush(); ob_flush();
        
        usleep(200000); // 200ms entre chaque produit
    }
    
    // Message final
    $_SESSION['htp_message'] = "✅ Actualisation terminée : $count_success succès, $errors erreur(s) sur $total produits";
    $_SESSION['htp_message_type'] = ($errors > 0) ? 'warning' : 'success';
    
    print '<div style="text-align:center;margin-top:20px;">';
    print '<a href="'.$_SERVER['PHP_SELF'].'" class="button button-ok" style="font-size:16px;padding:10px 20px;">← Retour aux produits</a>';
    print '</div>';
    
}

// ❌ SUPPRIMER UN PRODUIT
if ($action == 'delete_product' && $csrf_ok) {
    $rowid = GETPOST('rowid', 'int');
    if (htpwatchproducts_delete_product($db, $rowid)) {
        $_SESSION['htp_message'] = '✅ Produit désactivé (suppression logique)';
        $_SESSION['htp_message_type'] = 'success';
    } else {
        $_SESSION['htp_message'] = '❌ Erreur lors de la suppression';
        $_SESSION['htp_message_type'] = 'error';
    }
    
    // Pattern PRG : Redirection après POST
    header('Location: '.$_SERVER['PHP_SELF']);
}

// 🧪 TESTER PRIX
if ($action == 'test_price' && $csrf_ok) {
    $url      = trim(GETPOST('url', 'alpha'));
    $supplier = GETPOST('supplier', 'alpha');
    
    // ✅ INGRAM MICRO - Test via API OAuth2
    if ($supplier == 'ingrammicro') {
        $test_result = htpwatchproducts_scrape_ingram($url, $db, $DEBUG_ERRORS);
        $test_price = $test_result['success'] ? htpwatchproducts_format_price($test_result['price']) : '❌ '.$test_result['message'];
    }
    // ✅ AUTRES FOURNISSEURS - Scraping classique
    else {
        $login    = $conf->global->{'HTP_'.$supplier.'_LOGIN'} ?? '';
        $password = $conf->global->{'HTP_'.$supplier.'_PASSWORD'} ?? '';
        $login_url = $conf->global->{'HTP_'.$supplier.'_URL'} ?? '';
        
        if ($url && $login && $password && $login_url) {
            $test_result = _htpwatchproducts_scrape_price($url, $supplier, $login, $password, $login_url, $DEBUG_ERRORS);
            $test_price = $test_result['success'] ? htpwatchproducts_format_price($test_result['price']) : '❌ '.$test_result['message'];
        } else {
            $test_price = '❌ Config fournisseur manquante (vérifiez Setup)';
            $test_result = ['success' => false];
        }
    }
}

// =================================================================
// 🧾 FORMULAIRE "AJOUTER UN PRODUIT"
// =================================================================
print '<br>';
print '<div style="background:#fff;padding:15px;margin:10px 0;border:1px solid #ddd;border-radius:4px;">';
print '<h3 style="margin-top:0;">🧾 Ajouter un produit</h3>';

if ($action == 'test_price' && isset($test_price)) {
    $bg_color = ($test_result && $test_result['success']) ? '#d4edda' : '#f8d7da';
    $text_color = ($test_result && $test_result['success']) ? '#155724' : '#721c24';
    print '<div style="background:'.$bg_color.';color:'.$text_color.';padding:10px;margin-bottom:10px;border-radius:3px;">';
    print '<b>👉 Résultat test :</b> '.$test_price;
    print '</div>';
}

print '<form method="post" style="margin-bottom:10px;" id="productForm">';
print '<input type="hidden" name="token" value="'.(function_exists('newToken') ? newToken() : '').'">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td style="width:120px;"><b>Nom produit</b></td>';
print '<td><input type="text" name="label" size="40" value="'.dol_escape_htmltag(GETPOST('label','alpha')).'" placeholder="Ex: RTX 5060 8Go" required></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td><b>URL produit</b></td>';
print '<td><input type="url" name="url" size="80" value="'.dol_escape_htmltag(GETPOST('url','alpha')).'" placeholder="https://..." required></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td><b>Fournisseur</b></td>';
print '<td>';
print '<select name="supplier" required>';
print '<option value="asialand" '.(GETPOST('supplier','alpha')=='asialand'?'selected':'').'>Asialand</option>';
print '<option value="acadia" '.(GETPOST('supplier','alpha')=='acadia'?'selected':'').'>Acadia</option>';
print '<option value="espacepc" '.(GETPOST('supplier','alpha')=='espacepc'?'selected':'').'>EspacePC</option>';
print '<option value="ingrammicro" '.(GETPOST('supplier','alpha')=='ingrammicro'?'selected':'').'>Ingram Micro</option>';
print '</select>';
print '</td>';
print '</tr>';
print '<tr><td colspan="2" style="padding-top:10px;">';
print '<input type="submit" name="action" value="test_price" class="button" style="margin-right:10px;">';
print '<input type="submit" name="action" value="add_product" class="button button-ok">';
print '<button type="button" class="button" style="margin-left:10px;" onclick="document.getElementById(\'productForm\').reset();">Vider</button>';
print '</td></tr>';
print '</table>';
print '</form>';
print '</div>';

// =================================================================
// 📦 TABLEAU "PRODUITS SURVEILLÉS"
// =================================================================
print '<div style="background:#fff;padding:15px;margin:10px 0;border:1px solid #ddd;border-radius:4px;">';
print '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">';
print '<h3 style="margin:0;">📦 Produits surveillés</h3>';

// Bouton Rafraîchir tout
$products_count = count(htpwatchproducts_list_products($db));
if ($products_count > 0) {
    print '<form method="post" style="display:inline;">';
    print '<input type="hidden" name="token" value="'.(function_exists('newToken') ? newToken() : '').'">';
    print '<input type="hidden" name="action" value="refresh_all">';
    print '<button type="submit" class="button button-small" title="Actualiser tous les prix" style="background:#28a745;color:#fff;border:none;padding:8px 15px;border-radius:3px;cursor:pointer;" onmouseover="this.style.background=\'#218838\'" onmouseout="this.style.background=\'#28a745\'">';
    print '🔄 Tout actualiser ('.$products_count.')';
    print '</button>';
    print '</form>';
}

print '</div>';

if ($message) {
    $bg_color = ($message_type == 'success') ? '#d4edda' : (($message_type == 'warning') ? '#fff3cd' : '#f8d7da');
    $text_color = ($message_type == 'success') ? '#155724' : (($message_type == 'warning') ? '#856404' : '#721c24');
    print '<div style="background:'.$bg_color.';color:'.$text_color.';padding:10px;margin-bottom:15px;border-radius:3px;">';
    print $message;
    print '</div>';
}

$products = htpwatchproducts_list_products($db);

if (empty($products)) {
    print '<p style="color:#666;font-style:italic;">Aucun produit surveillé pour le moment. Ajoutez-en un ci-dessus 👆</p>';
} else {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Nom produit</th>';
    print '<th>Fournisseur</th>';
    print '<th>URL</th>';
    print '<th>Prix HT</th>';
    print '<th style="text-align:center;">Variation</th>';
    print '<th>Dernière MAJ</th>';
    print '<th style="text-align:center;">Actions</th>';
    print '<th style="text-align:center;">Suppr.</th>';
    print '</tr>';
    
    foreach ($products as $prod) {
        $price_display = htpwatchproducts_format_price($prod->last_price);
        $last_check_display = $prod->last_check ? dol_print_date(strtotime($prod->last_check), 'dayhour') : '<span style="color:#999;">–</span>';
        
        // ✅ Badge fournisseur pour les 4 fournisseurs
        if ($prod->supplier == 'asialand') {
            $supplier_badge = '<span style="background:#007bff;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">Asialand</span>';
        } elseif ($prod->supplier == 'acadia') {
            $supplier_badge = '<span style="background:#28a745;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">Acadia</span>';
        } elseif ($prod->supplier == 'espacepc') {
            $supplier_badge = '<span style="background:#6f42c1;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">EspacePC</span>';
        } else {
            $supplier_badge = '<span style="background:#ff6b35;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;">Ingram Micro</span>';
        }
        
        $variation = get_price_variation($prod->price_history);
        
        print '<tr class="oddeven">';
        print '<td><b>'.dol_escape_htmltag($prod->label).'</b></td>';
        print '<td>'.$supplier_badge.'</td>';
        $url_short = strlen($prod->url) > 50 ? substr($prod->url, 0, 47).'...' : $prod->url;
        print '<td><a href="'.dol_escape_htmltag($prod->url).'" target="_blank" title="'.dol_escape_htmltag($prod->url).'">'.dol_escape_htmltag($url_short).'</a></td>';
        print '<td style="text-align:right;font-weight:500;">'.$price_display.'</td>';
        print '<td style="text-align:center;font-size:18px;" title="'.$variation['text'].'"><span style="color:'.$variation['color'].';">'.$variation['icon'].'</span></td>';
        print '<td style="text-align:center;">'.$last_check_display.'</td>';
        print '<td style="text-align:center;">';
        print '<form method="post" style="display:inline;">';
        print '<input type="hidden" name="token" value="'.(function_exists('newToken') ? newToken() : '').'">';
        print '<input type="hidden" name="rowid" value="'.$prod->rowid.'">';
        print '<input type="hidden" name="action" value="refresh_price">';
        print '<button type="submit" class="button button-small" title="Actualiser le prix">🔄</button>';
        print '</form>';
        print '</td>';
        print '<td style="text-align:center;">';
        print '<form method="post" style="display:inline;" onsubmit="return confirm(\'Désactiver ce produit ?\');">';
        print '<input type="hidden" name="token" value="'.(function_exists('newToken') ? newToken() : '').'">';
        print '<input type="hidden" name="rowid" value="'.$prod->rowid.'">';
        print '<input type="hidden" name="action" value="delete_product">';
        print '<button type="submit" class="button button-small button-delete" title="Désactiver">❌</button>';
        print '</form>';
        print '</td>';
        print '</tr>';
    }
    print '</table>';
}
print '</div>';

// =================================================================
// ℹ️ INFO FUTUR (price_history JSON) - DEBUG ONLY
// =================================================================
if ($DEBUG_DEV) {
    print '<div style="background:#fff3cd;padding:10px;margin:10px 0;border-left:4px solid #ffc107;font-size:12px;">';
    print '<b>💡 Info développeur :</b> Le champ <code>price_history</code> est prêt en JSON pour futures évolutions ';
    print '(graphique, alertes variation, export CSV, etc.). Format : <code>[{"date":"...","price":123.45}]</code>';
    print '</div>';
}

llxFooter();