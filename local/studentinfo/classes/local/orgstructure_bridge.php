<?php
namespace local_studentinfo\local;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;

class orgstructure_bridge {
    public static function is_available(): bool {
        return (bool) \core_component::get_plugin_directory('local', 'orgstructure');
    }
    private static function table_exists(string $tablename): bool {
        global $DB;
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }
    public static function ou_options(string $type='college'): array {
        global $DB;
        if (!self::is_available() || !self::table_exists('local_org_unit')) { return []; }
        return $DB->get_records_menu('local_org_unit', ['type'=>$type], 'name', 'id,name');
    }
    public static function ou_name(int $ouid, string $type='college'): string {
        if (!$ouid) { return 'All OUs'; }
        global $DB;
        if (!self::table_exists('local_org_unit')) { return 'All OUs'; }
        return (string) $DB->get_field('local_org_unit', 'name', ['id'=>$ouid], IGNORE_MISSING) ?: 'All OUs';
    }
    public static function user_in_ou(int $userid, int $ouid): bool {
        global $DB;
        if (!$ouid || !self::table_exists('local_org_member')) { return true; }
        return $DB->record_exists('local_org_member', ['userid'=>$userid, 'orgunitid'=>$ouid]);
    }
    public static function ou_scope_for_user_alias(string $useraliasdotid, int $ouid): array {
        if (!$ouid || !self::table_exists('local_org_member')) { return ['sql'=>'','params'=>[]]; }
        return ['sql'=>"EXISTS (SELECT 1 FROM {local_org_member} lom WHERE lom.userid = {$useraliasdotid} AND lom.orgunitid = :ou)",
                'params'=>['ou'=>$ouid]];
    }
    public static function render_ou_bar(int $currentou, array $extras=[], string $label='OU: ', string $type='college'): string {
        $ous = self::ou_options($type);
        $out = html_writer::start_div('lib-oubar');
        $out .= html_writer::start_tag('form', ['method'=>'get','class'=>'d-flex align-items-center gap-2']);
        $out .= html_writer::tag('label', s($label), ['class'=>'me-2']);
        $attrs = ['name'=>'ou','class'=>'form-select lib-eq-height','onchange'=>'this.form.submit()','style'=>'min-width:260px'];
        $out .= html_writer::start_tag('select', $attrs);
        $out .= html_writer::tag('option','— All OUs —',['value'=>0,'selected'=>($currentou==0?'selected':null)]);
        foreach ($ous as $id=>$name) {
            $out .= html_writer::tag('option', s($name), ['value'=>(int)$id, 'selected'=>((int)$currentou===(int)$id?'selected':null)]);
        }
        $out .= html_writer::end_tag('select');
        foreach ($extras as $k=>$v) { $out .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>$k,'value'=>s((string)$v)]); }
        $out .= html_writer::end_tag('form');
        $out .= html_writer::end_div();
        return $out;
    }
    public static function with_ou(moodle_url $url, int $ou): moodle_url { if ($ou) { $url->param('ou', $ou); } return $url; }
    public static function ou_banner(int $ouid, string $prefix='Scope'): string {
        $name = self::ou_name($ouid);
        return html_writer::div(
            html_writer::tag('strong', s($prefix).': ') . html_writer::span(s($name)),
            'alert alert-info', ['role'=>'status','style'=>'margin:10px 0;']
        );
    }
}
