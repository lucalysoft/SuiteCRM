<?php
function install_colourSelector() {

    require_once('modules/Administration/Administration.php');

    global $sugar_config;

    $sugar_config['colourselector']['version'] = '1.0';
    if(!isset($sugar_config['colourselector']['menu'])) $sugar_config['colourselector']['menu'] = '#f10202';
    if(!isset($sugar_config['colourselector']['menufrom'])) $sugar_config['colourselector']['menufrom'] = '#f10202';
    if(!isset($sugar_config['colourselector']['menuto'])) $sugar_config['colourselector']['menuto'] = '#f10202';
    if(!isset($sugar_config['colourselector']['menufont'])) $sugar_config['colourselector']['menufont'] = '#f10202';
    if(!isset($sugar_config['colourselector']['menubrd'])) $sugar_config['colourselector']['menubrd'] = '#f10202';
    if(!isset($sugar_config['colourselector']['pageheader'])) $sugar_config['colourselector']['pageheader'] = '#f10202';
    if(!isset($sugar_config['colourselector']['pagelink'])) $sugar_config['colourselector']['pagelink'] = '#f10202';
    if(!isset($sugar_config['colourselector']['dashlet'])) $sugar_config['colourselector']['dashlet'] = '#f10202';
    if(!isset($sugar_config['colourselector']['button1'])) $sugar_config['colourselector']['button1'] = '#ffffff';
    if(!isset($sugar_config['colourselector']['button2'])) $sugar_config['colourselector']['button2'] = '#f3f3f3';
    if(!isset($sugar_config['colourselector']['button3'])) $sugar_config['colourselector']['button3'] = '#ededed';
    if(!isset($sugar_config['colourselector']['button4'])) $sugar_config['colourselector']['button4'] = '#ffffff';
    if(!isset($sugar_config['colourselector']['buttonhover'])) $sugar_config['colourselector']['buttonhover'] = '#f10202';

    ksort($sugar_config);
    write_array_to_file('sugar_config', $sugar_config, 'config.php');

}
?>
