<?php
// ==================================================================
// HTPWatchProducts - Fonctions métier (librairie)
// Dolibarr 23.0.2 - NAS Synology DS418
// ==================================================================
// Version: 20260605 Build: 2600
// Fichier: __FILE__ (portable)
// ==================================================================
$PATHFILE = __FILE__;
$VERSION  = '20260605';
$BUILD    = '2600';
$DEBUG_ERRORS = false;

if ($DEBUG_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// =================================================================
// 🔍 DEBUG HELPER
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
// 🔐 INGRAM MICRO - GESTION TOKEN OAUTH2
// =================================================================
/**
 * Récupère ou régénère le token OAuth2 Ingram Micro
 * Stocké dans llx_const : HTP_INGRAMMICRO_ACCESS_TOKEN + HTP_INGRAMMICRO_TOKEN_EXPIRE
 * 
 * @param object $db   Base de données
 * @param bool   $force_refresh  Forcer la régénération (par défaut false)
 * @return string|false  Token ou false si échec
 */
function htpwatchproducts_get_ingram_token($db, $force_refresh = false) {
    global $conf;
    
    $client_id = $conf->global->HTP_INGRAMMICRO_CLIENT_ID ?? '';
    $client_secret = $conf->global->HTP_INGRAMMICRO_CLIENT_SECRET ?? '';
    
    if (empty($client_id) || empty($client_secret)) {
        dol_syslog("HTPWatchProducts: Ingram Micro - Client ID ou Secret manquant", LOG_ERR);
        return false;
    }
    
    // Vérifier si token existe et n'est pas expiré
    if (!$force_refresh) {
        $stored_token = $conf->global->HTP_INGRAMMICRO_ACCESS_TOKEN ?? '';
        $stored_expire = $conf->global->HTP_INGRAMMICRO_TOKEN_EXPIRE ?? 0;
        
        if (!empty($stored_token) && $stored_expire > time()) {
            dol_syslog("HTPWatchProducts: Ingram Micro - Token réutilisé (expire dans ".($stored_expire - time())."s)", LOG_DEBUG);
            return $stored_token;
        }
    }
    
    // Régénérer le token
    dol_syslog("HTPWatchProducts: Ingram Micro - Régénération token OAuth2", LOG_INFO);
    
    $ch = curl_init('https://api.ingrammicro.com/oauth/oauth20/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials&client_id=$client_id&client_secret=$client_secret");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $token = $data['access_token'] ?? null;
    $expires_in = (int)($data['expires_in'] ?? 0);
    
    if (!$token) {
        dol_syslog("HTPWatchProducts: Ingram Micro - Échec obtention token (HTTP $http_code)", LOG_ERR);
        return false;
    }
    
    // Stocker token avec marge de sécurité (-60s)
    $expire_timestamp = time() + $expires_in - 60;
    
    dolibarr_set_const($db, 'HTP_INGRAMMICRO_ACCESS_TOKEN', $token, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'HTP_INGRAMMICRO_TOKEN_EXPIRE', $expire_timestamp, 'chaine', 0, '', $conf->entity);
    
    dol_syslog("HTPWatchProducts: Ingram Micro - Token régénéré (expire dans {$expires_in}s)", LOG_INFO);
    return $token;
}

// =================================================================
// 🔍 INGRAM MICRO - EXTRACTION PN DEPUIS URL
// =================================================================
/**
 * Extrait le Ingram Part Number depuis l'URL produit
 * Regex : /[?&]id=([A-Z0-9-]+)/i
 * 
 * @param string $url  URL produit Ingram Micro
 * @return string|false  PN ou false si non trouvé
 */
function htpwatchproducts_extract_ingram_pn($url) {
    if (preg_match('/[?&]id=([A-Z0-9-]+)/i', $url, $matches)) {
        return strtoupper($matches[1]);
    }
    return false;
}

// =================================================================
// 🕵️ INGRAM MICRO - SCRAPING API
// =================================================================
/**
 * Récupère le prix via l'API Ingram Micro
 * 
 * @param string $url   URL produit
 * @param object $db    Base de données
 * @param bool   $debug Mode debug
 * @return array  ['success' => bool, 'price' => float|null, 'message' => string]
 */
function htpwatchproducts_scrape_ingram($url, $db, $debug = false) {
    global $conf;
    
    htp_dbg('INGRAM - URL', $url, $debug);
    
    // 1. Extraire PN depuis URL
    $pn = htpwatchproducts_extract_ingram_pn($url);
    if (!$pn) {
        dol_syslog("HTPWatchProducts: Ingram Micro - PN non trouvé dans URL: $url", LOG_ERR);
        return ['success' => false, 'price' => null, 'message' => 'PN Ingram non trouvé dans l\'URL'];
    }
    htp_dbg('INGRAM - PN extrait', $pn, $debug);
    
    // 2. Récupérer token OAuth2
    $token = htpwatchproducts_get_ingram_token($db);
    if (!$token) {
        return ['success' => false, 'price' => null, 'message' => 'Token OAuth2 non obtenu'];
    }
    
    // 3. Préparer payload API
    $customer_number = $conf->global->HTP_INGRAMMICRO_CUSTOMER_NUMBER ?? '';
    if (empty($customer_number)) {
        dol_syslog("HTPWatchProducts: Ingram Micro - Customer Number manquant", LOG_ERR);
        return ['success' => false, 'price' => null, 'message' => 'Customer Number Ingram manquant'];
    }
    
    $payload = json_encode([
        'products' => [
            ['ingramPartNumber' => $pn, 'quantity' => 1]
        ]
    ]);
    
    // 4. Appel API priceandavailability
    $ch = curl_init('https://api.ingrammicro.com/resellers/v6/catalog/priceandavailability?includePricing=true&includeAvailability=true');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$token,
        'IM-CustomerNumber: '.$customer_number,
        'IM-CountryCode: FR',
        'IM-CorrelationID: htp-'.time(),
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    htp_dbg('INGRAM - Réponse API (HTTP '.$http_code.')', $response, $debug);
    
    $data = json_decode($response, true);
    
    // 5. Gérer erreur token expiré
    if ($http_code == 401 || isset($data['fault']['faultstring']) && strpos($data['fault']['faultstring'], 'Invalid Access Token') !== false) {
        dol_syslog("HTPWatchProducts: Ingram Micro - Token expiré, régénération et retry", LOG_WARNING);
        $token = htpwatchproducts_get_ingram_token($db, true);
        if (!$token) {
            return ['success' => false, 'price' => null, 'message' => 'Token OAuth2 régénération échouée'];
        }
        
        // Retry
        $ch = curl_init('https://api.ingrammicro.com/resellers/v6/catalog/priceandavailability?includePricing=true&includeAvailability=true');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'IM-CustomerNumber: '.$customer_number,
            'IM-CountryCode: FR',
            'IM-CorrelationID: htp-retry-'.time(),
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        htp_dbg('INGRAM - Réponse API après retry (HTTP '.$http_code.')', $response, $debug);
    }
    
    // 6. Extraire prix
    if (isset($data[0]['pricing']['customerPrice'])) {
        $price = (float)$data[0]['pricing']['customerPrice'];
        $description = $data[0]['description'] ?? 'N/A';
        dol_syslog("HTPWatchProducts: Ingram Micro - Prix trouvé: $price € pour PN $pn ($description)", LOG_INFO);
        return ['success' => true, 'price' => $price, 'message' => 'Prix Ingram trouvé'];
    }
    
    // 7. Gérer produit non trouvé
    if (isset($data[0]['productStatusCode']) && $data[0]['productStatusCode'] == 'E') {
        $error_msg = $data[0]['productStatusMessage'] ?? 'Produit non trouvé';
        dol_syslog("HTPWatchProducts: Ingram Micro - Erreur: $error_msg", LOG_WARNING);
        return ['success' => false, 'price' => null, 'message' => "Produit Ingram non trouvé: $error_msg"];
    }
    
    dol_syslog("HTPWatchProducts: Ingram Micro - Prix non extrait de la réponse", LOG_ERR);
    return ['success' => false, 'price' => null, 'message' => 'Prix Ingram non extrait (réponse API invalide)'];
}

// =================================================================
// ➕ AJOUTER UN PRODUIT
// =================================================================
function htpwatchproducts_add_product($db, $label, $url, $supplier, $user) {
    global $conf;
    $supplier = strtolower($supplier);
    
    // ✅ Validation incluant ingrammicro
    if (empty($label) || empty($url) || !in_array($supplier, ['asialand', 'acadia', 'espacepc', 'ingrammicro'])) {
        error_log("HTPWATCH VALIDATION FAILED: label='$label' supplier='$supplier'");
        return -1;
    }
    
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return -2;
    }
    
    $now = dol_now();
    $user_id = (isset($user) && isset($user->id) && $user->id > 0) ? (int)$user->id : 'NULL';
    
    $sql = "INSERT INTO llx_htpwatchproducts_prod (";
    $sql .= "label, url, supplier, date_creation, fk_user_creat, status";
    $sql .= ") VALUES (";
    $sql .= "'".$db->escape($label)."', ";
    $sql .= "'".$db->escape($url)."', ";
    $sql .= "'".$db->escape($supplier)."', ";
    $sql .= "'".date('Y-m-d H:i:s', $now)."', ";
    $sql .= $user_id.", ";
    $sql .= "1";
    $sql .= ")";
    
    $res = $db->query($sql);
    if ($res) {
        return $db->last_insert_id('llx_htpwatchproducts_prod');
    }
    
    print '<div style="position:fixed;bottom:0;left:0;right:0;background:#c00;color:#fff;padding:10px;font-family:monospace;font-size:11px;z-index:9999;white-space:pre-wrap;">';
    print "<b>🔴 SQL ERROR</b>\n";
    print "Erreur: ".$db->lasterror()."\n";
    print "Requête: ".htmlspecialchars($sql)."\n";
    print '</div><div style="height:100px;"></div>';
    return -3;
}

// =================================================================
// 📋 LISTE DES PRODUITS
// =================================================================
function htpwatchproducts_list_products($db, $supplier_filter = null) {
    $products = array();
    
    $sql = "SELECT rowid, label, url, supplier, last_price, last_check, price_history, date_creation, status";
    $sql .= " FROM llx_htpwatchproducts_prod";
    $sql .= " WHERE status = 1";
    
    // ✅ Filtre incluant ingrammicro
    if ($supplier_filter && in_array(strtolower($supplier_filter), ['asialand', 'acadia', 'espacepc', 'ingrammicro'])) {
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
function htpwatchproducts_refresh_price($db, $rowid, $debug = false) {
    global $conf;
    
    $product = htpwatchproducts_fetch_product($db, $rowid);
    if (!$product) {
        return ['success' => false, 'price' => null, 'message' => 'Produit non trouvé'];
    }
    
    // ✅ INGRAM MICRO - Traitement spécifique
    if ($product->supplier == 'ingrammicro') {
        $result = htpwatchproducts_scrape_ingram($product->url, $db, $debug);
        
        if ($result['success']) {
            $new_price = $result['price'];
            $now = dol_now();
            
            $sql = "UPDATE llx_htpwatchproducts_prod SET ";
            $sql .= "last_price = ".(float)$new_price.", ";
            $sql .= "last_check = '".date('Y-m-d H:i:s', $now)."' ";
            $sql .= "WHERE rowid = ".(int)$rowid;
            
            if ($db->query($sql)) {
                _htpwatchproducts_add_to_history($db, $rowid, $new_price, $now);
                return ['success' => true, 'price' => $new_price, 'message' => 'Prix Ingram mis à jour'];
            }
            return ['success' => false, 'price' => $new_price, 'message' => 'Erreur MAJ base'];
        }
        return $result;
    }
    
    // ✅ AUTRES FOURNISSEURS - Scraping classique
    $login    = $conf->global->{'HTP_'.$product->supplier.'_LOGIN'} ?? '';
    $password = $conf->global->{'HTP_'.$product->supplier.'_PASSWORD'} ?? '';
    $login_url = $conf->global->{'HTP_'.$product->supplier.'_URL'} ?? '';
    
    if (empty($login) || empty($password) || empty($login_url)) {
        return ['success' => false, 'price' => null, 'message' => 'Config fournisseur manquante'];
    }
    
    $result = _htpwatchproducts_scrape_price($product->url, $product->supplier, $login, $password, $login_url, $debug);
    
    if ($result['success']) {
        $new_price = $result['price'];
        $now = dol_now();
        
        $sql = "UPDATE llx_htpwatchproducts_prod SET ";
        $sql .= "last_price = ".(float)$new_price.", ";
        $sql .= "last_check = '".date('Y-m-d H:i:s', $now)."' ";
        $sql .= "WHERE rowid = ".(int)$rowid;
        
        if ($db->query($sql)) {
            _htpwatchproducts_add_to_history($db, $rowid, $new_price, $now);
            return ['success' => true, 'price' => $new_price, 'message' => 'Prix mis à jour'];
        }
        return ['success' => false, 'price' => $new_price, 'message' => 'Erreur MAJ base'];
    }
    
    return $result;
}

// =================================================================
// ❌ SUPPRIMER UN PRODUIT
// =================================================================
function htpwatchproducts_delete_product($db, $rowid) {
    $sql = "UPDATE llx_htpwatchproducts_prod SET status = 0 WHERE rowid = ".(int)$rowid;
    return (bool)$db->query($sql);
}

// =================================================================
// 🕵️ SCRAPING INTERNE (ASIALAND/ACADIA/ESPACEPC)
// =================================================================
function _htpwatchproducts_scrape_price($url, $supplier, $login, $password, $login_url, $debug = false) {
    htp_dbg('SCRAPE START - '.$supplier.' - '.$url, '', $debug);
    
    $html = @file_get_contents($login_url);
    if (!$html) {
        return ['success' => false, 'price' => null, 'message' => 'Erreur récupération page login'];
    }
    
    htp_dbg('HTML LOGIN PAGE', $html, $debug);
    
    $cookie = tempnam(sys_get_temp_dir(), 'htp_ck_');
    
    // ✅ ESPACEPC - Login spécifique
    if ($supplier == 'espacepc') {
        $ch = curl_init('https://www.espacepc.com/identification');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "in_login=$login&in_password=$password&action=identification");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_REFERER, 'https://www.espacepc.com/');
        curl_exec($ch);
        curl_close($ch);
    }
    // ✅ ASIALAND/ACADIA - Login Prestashop
    else {
        preg_match('/"token":"([^"]+)"/', $html, $m);
        $token = $m[1] ?? '';
        
        if (!$token) {
            return ['success' => false, 'price' => null, 'message' => 'Token Prestashop non trouvé'];
        }
        
        htp_dbg('TOKEN EXTRAIT', $token, $debug);
        
        $ch = curl_init($login_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "email=$login&password=$password&submitLogin=1&back=my-account&token=$token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_exec($ch);
        curl_close($ch);
    }
    
    // 3️⃣ GET page produit
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $html = curl_exec($ch);
    curl_close($ch);
    @unlink($cookie);
    
    if (!$html) {
        return ['success' => false, 'price' => null, 'message' => 'Erreur récupération page produit'];
    }
    
    htp_dbg('HTML PRODUIT RAW (1000 premiers chars)', substr($html, 0, 1000), $debug);
    $html = html_entity_decode($html);
    htp_dbg('HTML PRODUIT DECODED (1000 premiers chars)', substr($html, 0, 1000), $debug);
    
    // 4️⃣ PARSING selon fournisseur
    $price = null;
    
    // ✅ ESPACEPC
    if ($supplier == 'espacepc') {
        if (preg_match('/soloprix_normal">([0-9]+).*?soloprix_cents">,([0-9]+)/s', $html, $m)) {
            $price = (float)($m[1] . '.' . $m[2]);
            htp_dbg('ESPACEPC - Prix trouvé', $price, $debug);
        } else {
            htp_dbg('ESPACEPC - Regex échouée', 'REGEX FAILED', $debug);
        }
    }
    // ✅ ACADIA
    if ($supplier == 'acadia') {
        if (preg_match('/"price_tax_exc":([0-9\.]+)/', $html, $m)) {
            $price = (float)$m[1];
            htp_dbg('ACADIA - price_tax_exc trouvé', $price, $debug);
        }
    }
    // ✅ ASIALAND
    if ($supplier == 'asialand') {
        if (preg_match('/Prix HT.*?([0-9]+,[0-9]{2})/s', $html, $m)) {
            $price = (float)str_replace(',', '.', $m[1]);
            htp_dbg('ASIALAND - Prix HT trouvé', $price, $debug);
        }
    }
    
    if ($price !== null && $price > 0) {
        return ['success' => true, 'price' => $price, 'message' => 'Prix trouvé'];
    }
    
    return ['success' => false, 'price' => null, 'message' => 'Prix non extrait (regex)'];
}

// =================================================================
// 📚 AJOUT À L'HISTORIQUE JSON
// =================================================================
function _htpwatchproducts_add_to_history($db, $rowid, $price, $now) {
    $product = htpwatchproducts_fetch_product($db, $rowid);
    $history = [];
    
    if (!empty($product->price_history)) {
        $decoded = json_decode($product->price_history, true);
        if (is_array($decoded)) {
            $history = $decoded;
        }
    }
    
    $history[] = ['date' => dol_print_date($now, 'dayhour'), 'price' => (float)$price, 'source' => 'scraping'];
    
    if (count($history) > 50) {
        $history = array_slice($history, -50);
    }
    
    $sql = "UPDATE llx_htpwatchproducts_prod SET price_history = '".$db->escape(json_encode($history, JSON_UNESCAPED_UNICODE))."'";
    $sql .= " WHERE rowid = ".(int)$rowid;
    
    return (bool)$db->query($sql);
}

// =================================================================
// 🎨 FORMATAGE PRIX
// =================================================================
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