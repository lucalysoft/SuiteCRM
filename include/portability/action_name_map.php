<?php

$action_name_map = [
    'index' => 'index',
    'multieditview' => 'multieditview',
    'DetailView' => 'record',
    'EditView' => 'edit',
    'ListView' => 'list',
    'Popup' => 'popup',
    'vcard' => 'vcard',
    'ImportVCard' => 'importvcard',
    'modulelistmenu' => 'modulelistmenu',
    'favorites' => 'favorites',
    'noaccess' => 'noaccess',
    'Step1' => 'step1',
    'Step2' => 'step2',
    'ComposeView' => 'compose',
    'WizardHome' => 'wizard-home',
    'WizardEmailSetup' => 'wizard-email-setup',
    'CampaignDiagnostic' => 'diagnostic',
    'WebToLeadCreation' => 'web-to-lead',
    'ResourceList' => 'resource-list',
    'quick_radius' => 'quick-radius',
    'Login' => 'login',
    'Authenticate' => 'authenticate',
    'Upgrade' => 'upgrade',
    'repair' => 'repair',
    'expandDatabase' => 'expand-database',
    'UpgradeAccess' => 'upgrade-access',
    'RebuildConfig' => 'rebuild-config',
    'RebuildRelationship' => 'rebuild-relationship',
    'RebuildSchedulers' => 'rebuild-schedulers',
    'RebuildDashlets' => 'rebuild-dashlets',
    'RebuildJSLang' => 'rebuild-js-lang',
    'RepairJSFile' => 'repair-js-file',
    'RepairFieldCasing' => 'repair-field-casing',
    'install_actions' => 'install-actions',
    'RepairIE' => 'repair-ie',
    'SyncInboundEmailAccounts' => 'sync-inbound-email-accounts',
    'RepairXSS' => 'repair-xss',
    'RepairActivities' => 'repair-activities',
    'RepairSeedUsers' => 'repair-seed-users',
    'RepairUploadFolder' => 'repair-upload-folder',
    'About' => 'about'
];

if (file_exists('custom/application/Ext/ActionNameMap/action_name_map.ext.php')) {
    include('custom/application/Ext/ActionNameMap/action_name_map.ext.php');
}
