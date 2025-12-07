<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Install hook for local_studentlookup.
 * Pre-seed student types, services, kor/regiment and ranks.
 */
function xmldb_local_studentlookup_install() {
    global $DB;

    $now = time();

    // 1. Student types.
    if (!$DB->record_exists('local_studentlookup_type', [])) {
        $local = (object)[
            'name'         => 'Local',
            'code'         => 'LOCAL',
            'sortorder'    => 0,
            'active'       => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ];
        $overseas = (object)[
            'name'         => 'Overseas',
            'code'         => 'OVERSEAS',
            'sortorder'    => 1,
            'active'       => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
            'usermodified' => 0,
        ];

        $DB->insert_record('local_studentlookup_type', $local);
        $DB->insert_record('local_studentlookup_type', $overseas);
    }

    // Reload types and get IDs by code.
    $types = $DB->get_records_menu('local_studentlookup_type', null, '', 'code,id');
    $localid   = isset($types['LOCAL']) ? $types['LOCAL'] : null;
    $overseasid = isset($types['OVERSEAS']) ? $types['OVERSEAS'] : null;

    // 2. Services (Army, Navy, Air Force) – tied to Local.
    if ($localid && !$DB->record_exists('local_studentlookup_service', [])) {
        $services = [
            ['name' => 'Army',      'code' => 'ARMY',      'sortorder' => 0],
            ['name' => 'Navy',      'code' => 'NAVY',      'sortorder' => 1],
            ['name' => 'Air Force', 'code' => 'AIRFORCE',  'sortorder' => 2],
        ];

        foreach ($services as $s) {
            $rec = (object)[
                'studenttypeid' => $localid,
                'name'          => $s['name'],
                'code'          => $s['code'],
                'sortorder'     => $s['sortorder'],
                'active'        => 1,
                'timecreated'   => $now,
                'timemodified'  => $now,
                'usermodified'  => 0,
            ];
            $DB->insert_record('local_studentlookup_service', $rec);
        }
    }

    // Reload services and get IDs by code.
    $services = $DB->get_records_menu('local_studentlookup_service', null, '', 'code,id');
    $armyid      = isset($services['ARMY']) ? $services['ARMY'] : null;
    $navyid      = isset($services['NAVY']) ? $services['NAVY'] : null;
    $airforceid  = isset($services['AIRFORCE']) ? $services['AIRFORCE'] : null;

    // 3. Kor / Regimen – Army, Navy, Air Force.
    if (!$DB->record_exists('local_studentlookup_korregimen', [])) {

        // --- Army Kor / Regiment (official unit names, English descriptions omitted in DB) ---
        if ($armyid) {
            $armykor = [
                ['Rejimen Askar Melayu Diraja (RAMD)',      'RAMD'],
                ['Rejimen Renjer Diraja (RRD)',             'RRD'],
                ['Rejimen Sempadan (RS)',                   'RS'],
                ['Kor Armor Diraja (KAD)',                  'KAD'],
                ['Rejimen Artileri Diraja (RAD)',           'RAD'],
                ['Rejimen Askar Jurutera Diraja (RAJD)',    'RAJD'],
                ['Rejimen Semboyan Diraja (RSD)',           'RSD'],
                ['Kor Polis Tentera Diraja (KPTD)',         'KPTD'],
                ['Rejimen Askar Wataniah (RAW)',            'RAW'],
                ['Kor Risik Diraja (KRD)',                  'KRD'],
                ['Kor Perkhidmatan Diraja (KPD)',           'KPD'],
                ['Kor Ordnans Diraja (KOD)',                'KOD'],
                ['Kor Jurutera Letrik dan Jentera Diraja (KJLJD)', 'KJLJD'],
                ['Kor Kesihatan Diraja (KKD)',              'KKD'],
                ['Kor Agama Angkatan Tentera (KAGAT)',      'KAGAT'],
                ['Kor Perkhidmatan Am (KPA)',               'KPA'],
            ];

            $sort = 0;
            foreach ($armykor as $k) {
                $rec = (object)[
                    'serviceid'    => $armyid,
                    'name'         => $k[0],
                    'code'         => $k[1],
                    'sortorder'    => $sort++,
                    'active'       => 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                    'usermodified' => 0,
                ];
                $DB->insert_record('local_studentlookup_korregimen', $rec);
            }
        }

        // --- Navy Kor / Regimen (you said "navy ok" on earlier list) ---
        if ($navyid) {
            $navykor = [
                ['Fleet Command',                'FLEET'],
                ['Naval Special Forces (PASKAL)','PASKAL'],
                ['Fleet Support',                'FLEETSUP'],
                ['Naval Technical Branch',       'NAVTECH'],
            ];

            $sort = 0;
            foreach ($navykor as $k) {
                $rec = (object)[
                    'serviceid'    => $navyid,
                    'name'         => $k[0],
                    'code'         => $k[1],
                    'sortorder'    => $sort++,
                    'active'       => 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                    'usermodified' => 0,
                ];
                $DB->insert_record('local_studentlookup_korregimen', $rec);
            }
        }

        // --- Air Force Kor / Regimen (in English) ---
        if ($airforceid) {
            $afkor = [
                ['Pilot Branch',           'PILOT'],
                ['Engineering Branch',     'ENG'],
                ['Materiel Branch',        'MAT'],
                ['Administration Branch',  'ADMIN'],
                ['Air Traffic Control',    'ATC'],
                ['Air Defence',            'AIRDEF'],
                ['Fire & Rescue',          'FIRE'],
            ];

            $sort = 0;
            foreach ($afkor as $k) {
                $rec = (object)[
                    'serviceid'    => $airforceid,
                    'name'         => $k[0],
                    'code'         => $k[1],
                    'sortorder'    => $sort++,
                    'active'       => 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                    'usermodified' => 0,
                ];
                $DB->insert_record('local_studentlookup_korregimen', $rec);
            }
        }
    }

    // 4. Ranks – simple seed set per service.
    if (!$DB->record_exists('local_studentlookup_rank', [])) {

        // Army ranks.
        if ($armyid) {
            $armyranks = [
                ['Second Lieutenant',    '2LT',   10],
                ['Lieutenant',           'LT',    20],
                ['Captain',              'CPT',   30],
                ['Major',                'MAJ',   40],
                ['Lieutenant Colonel',   'LTCOL', 50],
                ['Colonel',              'COL',   60],
                ['Brigadier General',    'BRIGGEN', 70],
                ['Major General',        'MAJGEN', 80],
                ['Lieutenant General',   'LTGEN',  90],
                ['General',              'GEN',   100],
            ];

            foreach ($armyranks as $r) {
                $rec = (object)[
                    'serviceid'    => $armyid,
                    'name'         => $r[0],
                    'shortname'    => $r[1],
                    'ranklevel'    => $r[2],
                    'active'       => 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                    'usermodified' => 0,
                ];
                $DB->insert_record('local_studentlookup_rank', $rec);
            }
        }

        // Navy ranks.
        if ($navyid) {
            $navyranks = [
                ['Acting Sub Lieutenant', 'ASLt',  10],
                ['Sub Lieutenant',        'SLt',   20],
                ['Lieutenant',            'Lt',    30],
                ['Lieutenant Commander',  'Lt Cdr',40],
                ['Commander',             'Cdr',   50],
                ['Captain',               'Capt',  60],
                ['Commodore',             'Cdre',  70],
                ['Rear Admiral',          'R Adm', 80],
                ['Vice Admiral',          'V Adm', 90],
                ['Admiral',               'Adm',   100],
            ];

            foreach ($navyranks as $r) {
                $rec = (object)[
                    'serviceid'    => $navyid,
                    'name'         => $r[0],
                    'shortname'    => $r[1],
                    'ranklevel'    => $r[2],
                    'active'       => 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                    'usermodified' => 0,
                ];
                $DB->insert_record('local_studentlookup_rank', $rec);
            }
        }

        // Air Force ranks (mirroring Army naming, for now).
        if ($airforceid) {
            $afranks = [
                ['Second Lieutenant',    '2LT',   10],
                ['Lieutenant',           'LT',    20],
                ['Captain',              'CPT',   30],
                ['Major',                'MAJ',   40],
                ['Lieutenant Colonel',   'LTCOL', 50],
                ['Colonel',              'COL',   60],
                ['Brigadier General',    'BRIGGEN', 70],
                ['Major General',        'MAJGEN', 80],
                ['Lieutenant General',   'LTGEN',  90],
                ['General',              'GEN',   100],
            ];

            foreach ($afranks as $r) {
                $rec = (object)[
                    'serviceid'    => $airforceid,
                    'name'         => $r[0],
                    'shortname'    => $r[1],
                    'ranklevel'    => $r[2],
                    'active'       => 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                    'usermodified' => 0,
                ];
                $DB->insert_record('local_studentlookup_rank', $rec);
            }
        }
    }

    return true;
}
