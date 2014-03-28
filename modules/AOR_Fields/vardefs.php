<?php
/**
 * Advanced OpenReports, SugarCRM Reporting.
 * @package Advanced OpenReports for SugarCRM
 * @copyright SalesAgility Ltd http://www.salesagility.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU AFFERO GENERAL PUBLIC LICENSE
 * along with this program; if not, see http://www.gnu.org/licenses
 * or write to the Free Software Foundation,Inc., 51 Franklin Street,
 * Fifth Floor, Boston, MA 02110-1301  USA
 *
 * @author SalesAgility <info@salesagility.com>
 */

$dictionary['AOR_Field'] = array(
	'table'=>'aor_fields',
	'audited'=>false,
	'duplicate_merge'=>true,
	'fields'=>array (
        'aor_report_id' =>
        array (
            'required' => false,
            'name' => 'aor_report_id',
            'vname' => 'LBL_AOR_REPORT_ID',
            'type' => 'id',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => 0,
            'audited' => false,
            'reportable' => false,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => 36,
            'size' => '20',
        ),
        'field_order' =>
        array (
            'required' => false,
            'name' => 'field_order',
            'vname' => 'LBL_ORDER',
            'type' => 'int',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => '255',
            'size' => '20',
            'enable_range_search' => false,
            'disable_num_format' => '',
        ),
        'module_path' =>
        array (
            'name' => 'module_path',
            'type' => 'longtext',
            'vname' => 'LBL_MODULE_PATH',
            'isnull' => true,
        ),
        'field' =>
        array (
            'required' => false,
            'name' => 'field',
            'vname' => 'LBL_FIELD',
            'type' => 'enum',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => 100,
            'size' => '20',
            'options' => 'user_type_dom',
            'studio' => 'visible',
            'dependency' => false,
        ),
        'display' =>
        array (
            'required' => false,
            'name' => 'display',
            'vname' => 'LBL_DISPLAY',
            'type' => 'bool',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'studio' => 'visible',
        ),
        'link' =>
        array (
            'required' => false,
            'name' => 'link',
            'vname' => 'LBL_LINK',
            'type' => 'bool',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'studio' => 'visible',
        ),
        'label' =>
        array (
            'required' => false,
            'name' => 'label',
            'vname' => 'LBL_LABEL',
            'type' => 'varchar',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => '255',
            'size' => '20',
        ),
        'field_function' =>
        array (
            'required' => false,
            'name' => 'field_function',
            'vname' => 'LBL_FUNCTION',
            'type' => 'enum',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => 100,
            'size' => '20',
            'options' => 'aor_function_dom',
            'studio' => 'visible',
            'dependency' => false,
        ),
        'sort_by' =>
        array (
            'required' => false,
            'name' => 'sort_by',
            'vname' => 'LBL_SORT',
            'type' => 'enum',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => 100,
            'size' => '20',
            'options' => 'aor_sort_dom',
            'studio' => 'visible',
            'dependency' => false,
        ),
        'sort_order' =>
        array (
            'required' => false,
            'name' => 'sort_order',
            'vname' => 'LBL_SORT_ORDER',
            'type' => 'enum',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => 100,
            'size' => '20',
            'options' => 'aor_type_order_dom',
            'studio' => 'visible',
            'dependency' => false,
        ),
        'group_by' =>
        array (
            'required' => false,
            'name' => 'group_by',
            'vname' => 'LBL_GROUP',
            'type' => 'bool',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'studio' => 'visible',
        ),
        'group_order' =>
        array (
            'required' => false,
            'name' => 'group_order',
            'vname' => 'LBL_GROUP_ORDER',
            'type' => 'enum',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'len' => 100,
            'size' => '20',
            'options' => 'aor_type_order_dom',
            'studio' => 'visible',
            'dependency' => false,
        ),
        'group_display' =>
        array (
            'required' => false,
            'name' => 'group_display',
            'vname' => 'LBL_GROUP_DISPLAY',
            'type' => 'bool',
            'massupdate' => 0,
            'comments' => '',
            'help' => '',
            'importable' => 'true',
            'duplicate_merge' => 'disabled',
            'duplicate_merge_dom_value' => '0',
            'audited' => false,
            'reportable' => true,
            'unified_search' => false,
            'merge_filter' => 'disabled',
            'studio' => 'visible',
        ),
        'aor_reports' =>
        array (
            'name' => 'aor_reports',
            'type' => 'link',
            'relationship' => 'aor_report_aor_fields',
            'module'=>'AOR_Reports',
            'bean_name'=>'AOR_Reports',
            'source'=>'non-db',
        ),
    ),
    'relationships'=>array (
    ),
    'indices' => array(
        array(
            'name' => 'aor_fields_index_report_id',
            'type' => 'index',
            'fields' => array('aor_report_id'),
        ),
    ),
	'optimistic_locking'=>true,
		'unified_search'=>true,
);
if (!class_exists('VardefManager')){
        require_once('include/SugarObjects/VardefManager.php');
}
VardefManager::createVardef('AOR_Fields','AOR_Field', array('basic'));
