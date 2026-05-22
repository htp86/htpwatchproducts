<?php
/* ==================================================================
 * HTPWatchProducts - Classe principale du module autonome
 * Dolibarr 23.0.2 - NAS Synology DS418
 * ==================================================================
 * Version: 20260521 Build: 1700
 * Fichier: /volume1/web/dolibarr_test/htdocs/custom/htpwatchproducts/core/modules/modHTPWatchProducts.class.php
 * ================================================================== */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modHTPWatchProducts extends DolibarrModules
{
    function __construct($db)
    {
        global $langs;
        $this->db = $db;
        $this->numero = 500000;
        $this->rights_class = 'htpwatchproducts';
        $this->family = "htp";
        $this->name = 'HTPWatchProducts';
        $this->description = "Surveillance prix fournisseurs (Asialand, Acadia)";
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_HTPWATCHPRODUCTS';
        $this->picto = 'generic';
        $this->config_page_url = array("setup.php@htpwatchproducts");
        $this->phpmin = array(7,0);
        $this->module_position = 50;

        // MENUS
        $this->menu = array();
        $r = 0;

        $this->menu[$r++] = array(
            'fk_menu'   => '',
            'type'      => 'top',
            'titre'     => 'HTP Watch',
            'mainmenu'  => 'htpwatchproducts',
            'leftmenu'  => '',
            'url'       => '/custom/htpwatchproducts/admin/products.php',
            'langs'     => '',
            'position'  => 1000 + $r,
            'enabled'   => '1',
            'perms'     => '1',
            'target'    => '',
            'user'      => 0
        );

        $this->menu[$r++] = array(
            'fk_menu'   => 'fk_mainmenu=htpwatchproducts',
            'type'      => 'left',
            'titre'     => 'Produits',
            'mainmenu'  => 'htpwatchproducts',
            'leftmenu'  => 'products',
            'url'       => '/custom/htpwatchproducts/admin/products.php',
            'langs'     => '',
            'position'  => 10,
            'enabled'   => '1',
            'perms'     => '1',
            'target'    => '',
            'user'      => 0
        );

        $this->menu[$r++] = array(
            'fk_menu'   => 'fk_mainmenu=htpwatchproducts',
            'type'      => 'left',
            'titre'     => 'Configuration',
            'mainmenu'  => 'htpwatchproducts',
            'leftmenu'  => 'config',
            'url'       => '/custom/htpwatchproducts/admin/setup.php',
            'langs'     => '',
            'position'  => 20,
            'enabled'   => '1',
            'perms'     => '1',
            'target'    => '',
            'user'      => 0
        );
    }

    // =================================================================
    // 🚀 ACTIVATION : Dolibarr appelle CETTE méthode
    // =================================================================
    public function init($options = '')
    {
        global $db, $conf, $langs;
        
        $sql = array(); // Obligatoire, même vide
        
        dol_syslog("modHTPWatchProducts::init start", LOG_DEBUG);
        
        // ✅ CRITIQUE : chemin relatif à htdocs/, PAS au fichier PHP
        // Le dossier sql/ est dans /custom/htpwatchproducts/sql/
        // Donc le chemin relatif depuis htdocs/ est : /htpwatchproducts/sql/
        $this->_load_tables('/htpwatchproducts/sql/');
        
        // Appel parent : gère constants, menus, permissions
        $result = $this->_init($sql, $options);
        
        dol_syslog("modHTPWatchProducts::init finished with result=".$result, LOG_INFO);
        return $result;
    }

    // =================================================================
    // 🗑️ DÉSACTIVATION
    // =================================================================
    public function remove($options = '')
    {
        global $db, $conf, $langs;
        
        $sql = array(); // Obligatoire, même vide
        
        dol_syslog("modHTPWatchProducts::remove start", LOG_DEBUG);
        
        // Par défaut : on ne supprime pas la table (garde les données)
        // Décommente pour supprimer à la désactivation :
        // $db->query("DROP TABLE IF EXISTS ".MAIN_DB_PREFIX."htpwatchproducts_prod");
        
        $result = $this->_remove($sql, $options);
        
        dol_syslog("modHTPWatchProducts::remove finished with result=".$result, LOG_INFO);
        return $result;
    }
}