<?php
/**
 * A user defined trigger, to customize the application
 * A trigger will activate an action
 *
 */
class EmailRecipients extends cmdbAbstractObject
{
    public static function Init()
    {
        $aParams = array
        (
            "category" => "core/cmdb,view_in_gui,application,grant_by_profile",
            "key_type" => "autoincrement",
            "name_attcode" => "name",
            "state_attcode" => "",
            "reconc_keys" => array(),
            "db_table" => "emailrecipients",
            "db_key_field" => "id",
            "db_finalclass_field" => "",
            "display_template" => "",
        );
        MetaModel::Init_Params($aParams);
//        MetaModel::Init_InheritAttributes();

        MetaModel::Init_AddAttribute(new AttributeString("name", array("allowed_values"=>null, "sql"=>"name", "default_value"=>null, "is_null_allowed"=>false, "depends_on"=>array())));
        MetaModel::Init_AddAttribute(new AttributeOQL("oql", array("allowed_values"=>null, "sql"=>"oql", "default_value"=>null, "is_null_allowed"=>false, "depends_on"=>array())));
        MetaModel::Init_AddAttribute(new AttributeText("description", array("allowed_values"=>null, "sql"=>"description", "default_value"=>null, "is_null_allowed"=>false, "depends_on"=>array())));

        // Display lists
        MetaModel::Init_SetZListItems('details', array('name', 'description', 'oql')); // Attributes to be displayed for the complete details
//        MetaModel::Init_SetZListItems('list', array('name', 'description')); // Attributes to be displayed for a list

        MetaModel::Init_SetZListItems('standard_search', array('name', 'oql')); // Criteria of the std search form
    }
}

class lnkEmailRecipientsToActionEmail extends cmdbAbstractObject
{
    public static function Init()
    {
        $aParams = array
        (
            'category' => 'core/cmdb,view_in_gui,application',
            'key_type' => 'autoincrement',
            'is_link' => true,
            'name_attcode' => array('emailrecipients_id', 'action_id', 'field_type'),
            'state_attcode' => '',
            'reconc_keys' => array('emailrecipients_id', 'action_id', 'field_type'),
            'db_table' => 'lnkemailrecipientsaction',
            'db_key_field' => 'id',
            'db_finalclass_field' => '',
        );
        MetaModel::Init_Params($aParams);
        MetaModel::Init_InheritAttributes();

        MetaModel::Init_AddAttribute(new AttributeExternalKey("emailrecipients_id", array("targetclass"=>'EmailRecipients', "allowed_values"=>null, "sql"=>'emailrecipients_id', "is_null_allowed"=>false, "on_target_delete"=>DEL_AUTO, "depends_on"=>array(), "display_style"=>'select', "always_load_in_tables"=>false)));
        MetaModel::Init_AddAttribute(new AttributeExternalKey("action_id", array("targetclass"=>'Action', "allowed_values"=>null, "sql"=>'action_id', "is_null_allowed"=>false, "on_target_delete"=>DEL_AUTO, "depends_on"=>array(), "display_style"=>'select', "always_load_in_tables"=>false)));
        MetaModel::Init_AddAttribute(new AttributeEnum("field_type", array("allowed_values"=>new ValueSetEnum("to,cc,bcc"), "display_style"=>'list', "sql"=>'field_type', "default_value"=>'to', "is_null_allowed"=>false, "depends_on"=>array(), "always_load_in_tables"=>false, "tracking_level"=>ATTRIBUTE_TRACKING_ALL)));

        MetaModel::Init_SetZListItems('details', ['field_type', 'action_id','emailrecipients_id']);
        MetaModel::Init_SetZListItems('standard_search', array (
            0 => 'field_type',
        ));
        MetaModel::Init_SetZListItems('list', ['field_type', 'action_id','emailrecipients_id']);
    }
}