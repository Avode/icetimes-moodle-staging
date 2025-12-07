<?php
namespace local_organization\output;

defined('MOODLE_INTERNAL') || die();

class dashboard implements \renderable, \templatable {

    public function export_for_template(\renderer_base $output) {
        global $DB, $CFG;

        $total   = $DB->count_records('local_organization_ou', ['deleted' => 0]);
        $records = $DB->get_records('local_organization_ou', ['deleted' => 0], 'fullname ASC');

        // Collect all user IDs to load them in one go.
        $userids = [];
        foreach ($records as $r) {
            if (!empty($r->commandantuserid)) {
                $userids[] = (int)$r->commandantuserid;
            }
            if (!empty($r->adminuserid)) {
                $userids[] = (int)$r->adminuserid;
            }
        }
        $userids = array_unique($userids);

        $users = [];
        if (!empty($userids)) {
            $users = $DB->get_records_list('user', 'id', $userids);
        }

        $rows = [];
        foreach ($records as $r) {
            $cmdname  = '';
            $cmdemail = '';
            if (!empty($r->commandantuserid) && isset($users[$r->commandantuserid])) {
                $u        = $users[$r->commandantuserid];
                $cmdname  = fullname($u);
                $cmdemail = $u->email;
            }

            $adminname  = '';
            $adminemail = '';
            if (!empty($r->adminuserid) && isset($users[$r->adminuserid])) {
                $u          = $users[$r->adminuserid];
                $adminname  = fullname($u);
                $adminemail = $u->email;
            }

            $rows[] = [
                'id'           => $r->id,
                'fullname'     => format_string($r->fullname),
                'shortname'    => format_string($r->shortname),
                'oucode'       => s($r->oucode),
                'district'     => s($r->district),
                'state'        => s($r->state),
                'commandant'   => $cmdname,
                'commandantem' => $cmdemail,
                'admin'        => $adminname,
                'adminem'      => $adminemail,
            ];
        }

        return [
            'totalou' => $total,
            'units'   => $rows,
            'wwwroot' => $CFG->wwwroot,
        ];
    }
}
