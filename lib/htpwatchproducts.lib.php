<?php
// ==================================================================
// HTPWatchProducts - Fonctions métier (librairie)
// Dolibarr 23.0.2 - NAS Synology DS418
// ==================================================================
// Version: 20260521 Build: 1627
// Fichier: /volume1/web/dolibarr_test/htdocs/custom/htpwatchproducts/lib/htpwatchproducts.lib.php
// ==================================================================

$PATHFILE = '/volume1/web/dolibarr_test/htdocs/custom/htpwatchproducts/lib/htpwatchproducts.lib.php';
$VERSION  = '20260521';
$BUILD    = '1759';
$DEBUG_ERRORS = false;

if ($DEBUG_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// =================================================================
// 🔍 DEBUG HELPER (réutilisable)
// =================================================================
function htp_dbg($label, $value, $enabled) {
    if (!$enabled) return;
    print '<div style="background:#000;color:#0f0;padding:5px;margin:5px;font-size:11px;font-family:monospace;">';
    print '<b>🔧 '.$label.'</b><br>';
    if (is_string($value)) {
        print htmlspecialchars(substr($value, 0, 3000));
    } elseif (is_array($value) || is_object($value)) {
        print '<pre>'.print_r($value, true).'</pre>';
    } else {
        print htmlspecialchars(var_export($value, true));
    }
    print '</div>';
}

// =================================================================
// ➕ AJOUTER UN PRODUIT
// =================================================================
/**
 * Ajoute un produit à surveiller dans la base
 * @param DoliDB $db          Handler base Dolibarr
 * @param string $label       Nom du produit
 * @param string $url         URL complète du produit
 * @param string $supplier    'asialand' ou 'acadia'
 * @param User   $user        Utilisateur Dolibarr connecté
 * @return int                rowid du produit créé, ou -1 en erreur
 */
// =================================================================
// ➕ AJOUTER UN PRODUIT (CORRIGÉ)
// =================================================================
function htpwatchproducts_add_product($db, $label, $url, $supplier, $user) {
    global $conf;
    
    $supplier = strtolower($supplier);
    
    // Validation
    if (empty($label) || empty($url) || !in_array($supplier, ['asialand', 'acadia'])) {
        error_log("HTPWATCH VALIDATION FAILED: label='$label' supplier='$supplier'");
        return -1;
    }
    
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return -2;
    }
    
    $now = dol_now();
    
    // ✅ Gestion sécurisée de user->id (NULL si non défini)
    $user_id = (isset($user) && isset($user->id) && $user->id > 0) ? (int)$user->id : 'NULL';
    
    $sql = "INSERT INTO llx_htpwatchproducts_prod (";
    $sql .= "label, url, supplier, date_creation, fk_user_creat, status";
    $sql .= ") VALUES (";
    $sql .= "'".$db->escape($label)."', ";
    $sql .= "'".$db->escape($url)."', ";
    $sql .= "'".$db->escape($supplier)."', ";
    $sql .= "'".date('Y-m-d H:i:s', $now)."', ";
    $sql .= $user_id.", ";  // ✅ Soit un entier, soit le mot NULL (sans quotes)
    $sql .= "1";
    $sql .= ")";
    
    $res = $db->query($sql);
    if ($res) {
        return $db->last_insert_id('llx_htpwatchproducts_prod');
    }
    
    // ✅ DEBUG SQL AFFICHÉ À L'ÉCRAN (temporaire, à supprimer après)
    print '<div style="position:fixed;bottom:0;left:0;right:0;background:#c00;color:#fff;padding:10px;font-family:monospace;font-size:11px;z-index:9999;white-space:pre-wrap;">';
    print "<b>🔴 SQL ERROR</b>\n";
    print "Erreur: ".$db->lasterror()."\n";
    print "Requête: ".htmlspecialchars($sql)."\n";
    print "user_id: ".var_export($user_id, true)."\n";
    print "user object: ".(isset($user) ? 'OK' : 'NULL')."\n";
    print '</div><div style="height:100px;"></div>';
    
    return -3;
}

// =================================================================
// 📋 LISTE DES PRODUITS
// =================================================================
/**
 * Récupère la liste des produits actifs
 * @param DoliDB $db              Handler base Dolibarr
 * @param string $supplier_filter Optionnel : filtrer par fournisseur
 * @return array                  Tableau d'objets produits
 */
function htpwatchproducts_list_products($db, $supplier_filter = null) {
    $products = array();
    
    $sql = "SELECT rowid, label, url, supplier, last_price, last_check, price_history, date_creation, status";
    $sql .= " FROM llx_htpwatchproducts_prod";
    $sql .= " WHERE status = 1";
	if ($supplier_filter && in_array(strtolower($supplier_filter), ['asialand', 'acadia'])) {
		$sql .= " AND supplier = '".$db->escape(strtolower($supplier_filter))."'";
	}
    $sql .= " ORDER BY date_creation DESC";
    
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $products[] = $obj;
        }
        $db->free($resql);
    }
    return $products;
}

// =================================================================
// 🔍 FETCH UN PRODUIT
// =================================================================
/**
 * Récupère les infos d'un produit par son rowid
 * @param DoliDB $db     Handler base Dolibarr
 * @param int    $rowid  ID du produit
 * @return object|false  Objet produit ou false si non trouvé
 */
function htpwatchproducts_fetch_product($db, $rowid) {
    $sql = "SELECT rowid, label, url, supplier, last_price, last_check, price_history, date_creation, status";
    $sql .= " FROM llx_htpwatchproducts_prod";
    $sql .= " WHERE rowid = ".(int)$rowid;
    
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $db->free($resql);
        return $obj;
    }
    return false;
}

// =================================================================
// 🔄 ACTUALISER PRIX D'UN PRODUIT
// =================================================================
/**
 * Met à jour le prix d'un produit en relançant le scraping
 * @param DoliDB $db       Handler base Dolibarr
 * @param int    $rowid    ID du produit
 * @param bool   $debug    Activer le debug détaillé
 * @return array           ['success'=>bool, 'price'=>float|string, 'message'=>string]
 */
function htpwatchproducts_refresh_price($db, $rowid, $debug = false) {
    global $conf;
    
    $product = htpwatchproducts_fetch_product($db, $rowid);
    if (!$product) {
        return ['success' => false, 'price' => null, 'message' => 'Produit non trouvé'];
    }
    
    // Récupérer config fournisseur
    $login    = $conf->global->{'HTP_'.$product->supplier.'_LOGIN'} ?? '';
    $password = $conf->global->{'HTP_'.$product->supplier.'_PASSWORD'} ?? '';
    $login_url = $conf->global->{'HTP_'.$product->supplier.'_URL'} ?? '';
    
    if (empty($login) || empty($password) || empty($login_url)) {
        return ['success' => false, 'price' => null, 'message' => 'Config fournisseur manquante'];
    }
    
    // Appel scraping interne
    $result = _htpwatchproducts_scrape_price($product->url, $product->supplier, $login, $password, $login_url, $debug);
    
    if ($result['success']) {
        $new_price = $result['price'];
        $now = dol_now();
        
        // Mise à jour last_price + last_check
		$sql = "UPDATE llx_htpwatchproducts_prod SET ";
		$sql .= "last_price = ".(float)$new_price.", ";
		$sql .= "last_check = '".date('Y-m-d H:i:s', $now)."' ";
		$sql .= "WHERE rowid = ".(int)$rowid;

        if ($db->query($sql)) {
            // 🎁 Mise à jour historique JSON (préparation futur)
            _htpwatchproducts_add_to_history($db, $rowid, $new_price, $now);
            return ['success' => true, 'price' => $new_price, 'message' => 'Prix mis à jour'];
        }
        return ['success' => false, 'price' => $new_price, 'message' => 'Erreur MAJ base'];
    }
    
    return $result;
}

// =================================================================
// ❌ SUPPRIMER UN PRODUIT (logique : status=0)
// =================================================================
/**
 * Désactive un produit (suppression logique réversible)
 * @param DoliDB $db     Handler base Dolibarr
 * @param int    $rowid  ID du produit
 * @return bool          true si OK, false sinon
 */
function htpwatchproducts_delete_product($db, $rowid) {
    $sql = "UPDATE llx_htpwatchproducts_prod SET status = 0 WHERE rowid = ".(int)$rowid;
    return (bool)$db->query($sql);
}

// =================================================================
// 🕵️ SCRAPING INTERNE (réutilise ton code existant)
// =================================================================
/**
 * [INTERNE] Scraping prix - fonction cœur réutilisable
 * @param string $url        URL produit
 * @param string $supplier   'asialand' ou 'acadia'
 * @param string $login      Login fournisseur
 * @param string $password   Password fournisseur
 * @param string $login_url  URL page login
 * @param bool   $debug      Activer debug
 * @return array             ['success'=>bool, 'price'=>float|string, 'message'=>string]
 */
function _htpwatchproducts_scrape_price($url, $supplier, $login, $password, $login_url, $debug = false) {
    htp_dbg('SCRAPE START - '.$supplier.' - '.$url, '', $debug);
    
    // 1️⃣ GET login page + extraction token
    $html = @file_get_contents($login_url);
    if (!$html) {
        return ['success' => false, 'price' => null, 'message' => 'Erreur récupération page login'];
    }
    htp_dbg('HTML LOGIN PAGE', $html, $debug);
    
    preg_match('/"token":"([^"]+)"/', $html, $m);
    $token = $m[1] ?? '';
    if (!$token) {
        return ['success' => false, 'price' => null, 'message' => 'Token Prestashop non trouvé'];
    }
    htp_dbg('TOKEN EXTRAIT', $token, $debug);
    
    // 2️⃣ POST login avec cookies
    $cookie = tempnam(sys_get_temp_dir(), 'htp_ck_');
    $ch = curl_init($login_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "email=$login&password=$password&submitLogin=1&back=my-account&token=$token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_TIMEOUT => 30
    ]);
    $login_result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    htp_dbg('LOGIN HTTP CODE', $http_code, $debug);
    
    // 3️⃣ GET page produit avec session cookies
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_TIMEOUT => 30
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    @unlink($cookie); // nettoyage
    
    if (!$html) {
        return ['success' => false, 'price' => null, 'message' => 'Erreur récupération page produit'];
    }
    htp_dbg('HTML PRODUIT RAW (1000 premiers chars)', substr($html, 0, 1000), $debug);
    
    // ✅ DÉCODAGE ENTITÉS HTML (CRUCIAL pour Acadia)
    $html = html_entity_decode($html);
    htp_dbg('HTML PRODUIT DECODED (1000 premiers chars)', substr($html, 0, 1000), $debug);
    
    // 4️⃣ PARSING selon fournisseur
    $price = null;
    
    if ($supplier == 'acadia') {
        // 🔵 Acadia : prix dans JSON price_tax_exc
        if (preg_match('/"price_tax_exc":([0-9\.]+)/', $html, $m)) {
            $price = (float)$m[1];
            htp_dbg('ACADIA - price_tax_exc trouvé', $price, $debug);
        } else {
            htp_dbg('ACADIA - price_tax_exc NON trouvé', 'REGEX FAILED', $debug);
        }
    }
    
    if ($supplier == 'asialand') {
        // 🔵 Asialand : prix dans HTML visible "Prix HT XX,XX"
        if (preg_match('/Prix HT.*?([0-9]+,[0-9]{2})/s', $html, $m)) {
            $price = (float)str_replace(',', '.', $m[1]);
            htp_dbg('ASIALAND - Prix HT trouvé', $price, $debug);
        } else {
            htp_dbg('ASIALAND - Regex Prix HT échouée', 'REGEX FAILED', $debug);
        }
    }
    
    if ($price !== null && $price > 0) {
        return ['success' => true, 'price' => $price, 'message' => 'Prix trouvé'];
    }
    
    return ['success' => false, 'price' => null, 'message' => 'Prix non extrait (regex)'];
}

// =================================================================
// 📚 AJOUT À L'HISTORIQUE JSON (préparation futur)
// =================================================================
/**
 * [INTERNE] Ajoute une entrée à l'historique price_history (JSON)
 * @param DoliDB $db     Handler base Dolibarr
 * @param int    $rowid  ID du produit
 * @param float  $price  Nouveau prix
 * @param int    $now    Timestamp Dolibarr
 * @return bool          true si OK
 */
function _htpwatchproducts_add_to_history($db, $rowid, $price, $now) {
    // Récupérer historique existant
    $product = htpwatchproducts_fetch_product($db, $rowid);
    $history = [];
    
    if (!empty($product->price_history)) {
        $decoded = json_decode($product->price_history, true);
        if (is_array($decoded)) {
            $history = $decoded;
        }
    }
    
    // Ajouter nouvelle entrée (format standardisé)
    $history[] = [
        'date'   => dol_print_date($now, 'dayhour'),
        'price'  => (float)$price,
        'source' => 'scraping'
    ];
    
    // Limiter à 50 entries pour éviter gros JSON
    if (count($history) > 50) {
        $history = array_slice($history, -50);
    }
    
    // Mise à jour champ JSON
    $sql = "UPDATE llx_htpwatchproducts_prod SET price_history = '".$db->escape(json_encode($history, JSON_UNESCAPED_UNICODE))."'";
    $sql .= " WHERE rowid = ".(int)$rowid;
    
    return (bool)$db->query($sql);
}

// =================================================================
// 🎨 FORMATAGE PRIX (affichage HT avec € et 2 décimales)
// =================================================================
/**
 * Formate un prix pour affichage : "277.14 €"
 * @param float|string $price  Prix brut
 * @return string              Prix formaté
 */
function htpwatchproducts_format_price($price) {
    if ($price === null || $price === '' || $price === 'NOT FOUND') {
        return '<span style="color:#999;">–</span>';
    }
    $val = (float)$price;
    if ($val <= 0) {
        return '<span style="color:#999;">–</span>';
    }
    return number_format($val, 2, '.', '').' €';
}