<?php
/**
 * Amazon Invoices Module for FrontAccounting
 * Hooks configuration file
 */

$hooks = array(
    array(
        'name' => _('Amazon Invoices'),
        'title' => _('Amazon Invoice Management'),
        'description' => _('Download and process Amazon invoices'),
        'active' => 1,
    )
);

class hooks_amazon_invoices extends hooks 
{
    var $module_name = 'amazon_invoices';

    function install_options() 
    {
        global $path_to_root;
        
        switch($this->get_post_value('setting')) {
            case 'install':
                return array(
                    array('type' => 'text', 'name' => 'amazon_email', 'text' => _('Amazon Account Email'), 'default' => ''),
                    array('type' => 'password', 'name' => 'amazon_password', 'text' => _('Amazon Account Password'), 'default' => ''),
                    array('type' => 'text', 'name' => 'download_path', 'text' => _('Invoice Download Path'), 'default' => $path_to_root.'/tmp/amazon_invoices/'),
                    array('type' => 'text', 'name' => 'default_supplier', 'text' => _('Default Amazon Supplier ID'), 'default' => ''),
                );
        }
        return array();
    }

    function install_access() 
    {
        $security_areas = array(
            'SA_AMAZON_INVOICES' => array(_('Amazon Invoices'), 1),
            'SA_AMAZON_DOWNLOAD' => array(_('Download Amazon Invoices'), 2),
            'SA_AMAZON_PROCESS' => array(_('Process Amazon Invoices'), 3),
            'SA_AMAZON_MATCH' => array(_('Match Amazon Items'), 4),
            'SA_AMAZON_PAYMENTS' => array(_('Amazon Payment Allocation'), 5),
        );
        return $security_areas;
    }
}
