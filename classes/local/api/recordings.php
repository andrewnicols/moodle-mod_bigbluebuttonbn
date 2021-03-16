<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * BigBlueButtonBN internal API for recordings
 *
 * @package   mod_bigbluebuttonbn
 * @category  external
 * @copyright 2018 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_bigbluebuttonbn\local\api;
use context_course;
use context_module;
use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use mod_bigbluebuttonbn\local\bigbluebutton;
use mod_bigbluebuttonbn\local\broker;
use mod_bigbluebuttonbn\local\config;
use mod_bigbluebuttonbn\local\helpers\logs;
use mod_bigbluebuttonbn\local\helpers\recording;
use mod_bigbluebuttonbn\local\view;
use mod_bigbluebuttonbn\plugin;
use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die;
/**
 * BigBlueButtonBN external functions
 *
 * @package   mod_bigbluebuttonbn
 * @category  external
 * @copyright 2018 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recordings extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function recording_list_table_parameters() {
        return new external_function_parameters(
            array(
                'bigbluebuttonbnid' => new external_value(PARAM_INT, 'bigbluebuttonbn instance id')
            )
        );
    }

    /**
     * Get a list of recordings
     *
     * @param int $bigbluebuttonbnid the bigbluebuttonbn instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function recording_list_table($bigbluebuttonbnid) {
        global $DB, $PAGE;

        $params = self::validate_parameters(self::recording_list_table_parameters(),
            array(
                'bigbluebuttonbnid' => $bigbluebuttonbnid
            ));

        list($bbbsession, $enabledfeatures, $typeprofiles) = static::get_bbb_parameters_session($bigbluebuttonbnid);

        $tools = ['protect', 'publish', 'delete'];

        $recordings = recording::bigbluebutton_get_recordings_for_table_view($bbbsession, $enabledfeatures);
        $tabledata = array();
        $tabledata['activity'] = \mod_bigbluebuttonbn\local\bigbluebutton::bigbluebuttonbn_view_get_activity_status($bbbsession);
        $tabledata['ping_interval'] = (int) config::get('waitformoderator_ping_interval') * 1000;
        $tabledata['locale'] = \mod_bigbluebuttonbn\plugin::bigbluebuttonbn_get_localcode();
        $tabledata['profile_features'] = $typeprofiles[0]['features'];
        $tabledata['recordings_html'] = $bbbsession['bigbluebuttonbn']->recordings_html == '1';

        $data = array();
        // Build table content.
        if (isset($recordings) && !array_key_exists('messageKey', $recordings)) {
            // There are recordings for this meeting.
            foreach ($recordings as $recording) {
                $rowdata = recording::bigbluebuttonbn_get_recording_data_row($bbbsession, $recording, $tools);
                if (!empty($rowdata)) {
                    array_push($data, $rowdata);
                }
            }
        }

        $columns = array();
        // Initialize table headers.
        $columns[] = array('data' => 'playback', 'title' => get_string('view_recording_playback', 'bigbluebuttonbn'),
            'width' => '125px', 'type' => 'html'); // Note: here a strange bug noted whilst changing the columns, ref CONTRIB.
        $columns[] = array('data' => 'recording', 'title' => get_string('view_recording_name', 'bigbluebuttonbn'),
            'width' => '125px', 'html');
        $columns[] = array('data' => 'description', 'title' => get_string('view_recording_description', 'bigbluebuttonbn'),
            'sortable' => true, 'width' => '250px', 'type' => 'html');
        if (recording::bigbluebuttonbn_get_recording_data_preview_enabled($bbbsession)) {
            $columns[] = array('data' => 'preview', 'title' => get_string('view_recording_preview', 'bigbluebuttonbn'),
                'width' => '250px', 'type' => 'html');
        }
        $columns[] = array('data' => 'date', 'title' => get_string('view_recording_date', 'bigbluebuttonbn'),
            'sortable' => true, 'width' => '225px', 'type' => 'html');
        $columns[] = array('data' => 'duration', 'title' => get_string('view_recording_duration', 'bigbluebuttonbn'),
            'width' => '50px');
        if ($bbbsession['managerecordings']) {
            $columns[] = array('data' => 'actionbar', 'title' => get_string('view_recording_actionbar', 'bigbluebuttonbn'),
                'width' => '120px', 'type' => 'html');
        }

        $warnings = array();

        $result = array();
        $tabledata['columns'] = $columns;
        $tabledata['data'] = json_encode($data);
        $result['status'] = true;
        $result['tabledata'] = $tabledata;
        $result['warnings'] = array(); // TODO: add warning if needed.
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function recording_list_table_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'tabledata' => new external_single_structure(
                    array (
                        'activity' => new external_value(PARAM_ALPHA),
                        'ping_interval' => new external_value(PARAM_INT),
                        'locale' => new external_value(PARAM_TEXT),
                        'profile_features' => new external_multiple_structure(
                            new external_value(PARAM_TEXT)
                        ),
                        'recordings_html' => new external_value(PARAM_BOOL),
                        'columns' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'data' => new external_value(PARAM_ALPHA),
                                    'title' => new external_value(PARAM_TEXT),
                                    'width' => new external_value(PARAM_ALPHANUMEXT),
                                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Column type', VALUE_OPTIONAL),
                                    // See https://datatables.net/reference/option/columns.type
                                )
                            )
                        ),
                        'data' => new external_value(PARAM_RAW), // For now it will be json encoded.
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    protected static function get_bbb_parameters_session($bbbid) {
        global $PAGE;
        $viewinstance = view::bigbluebuttonbn_view_instance_bigbluebuttonbn($bbbid);
        if (!$viewinstance) {
            throw new moodle_exception('view_error_url_missing_parameters', plugin::COMPONENT);
        }

        $cm = $viewinstance['cm'];
        $course = $viewinstance['course'];
        $bigbluebuttonbn = $viewinstance['bigbluebuttonbn'];

        require_login($course, true, $cm);

        // In locallib.
        logs::bigbluebuttonbn_event_log(\mod_bigbluebuttonbn\event\events::$events['view'], $bigbluebuttonbn);

        // Additional info related to the course.
        $bbbsession['course'] = $course;
        $bbbsession['coursename'] = $course->fullname;
        $bbbsession['cm'] = $cm;
        $bbbsession['bigbluebuttonbn'] = $bigbluebuttonbn;
        // In locallib.
        bigbluebutton::view_bbbsession_set($PAGE->context, $bbbsession);
        $type = null;
        if (isset($bbbsession['bigbluebuttonbn']->type)) {
            $type = $bbbsession['bigbluebuttonbn']->type;
        }
        // Validates if the BigBlueButton server is working.
        $serverversion = bigbluebutton::bigbluebuttonbn_get_server_version();  // In locallib.
        if ($serverversion === null) {
            $errmsg = 'view_error_unable_join_student';
            $errurl = '/course/view.php';
            $errurlparams = ['id' => $bigbluebuttonbn->course];
            if ($bbbsession['administrator']) {
                $errmsg = 'view_error_unable_join';
                $errurl = '/admin/settings.php';
                $errurlparams = ['section' => 'modsettingbigbluebuttonbn'];
            } else if ($bbbsession['moderator']) {
                $errmsg = 'view_error_unable_join_teacher';
            }
            print_error($errmsg, plugin::COMPONENT, new moodle_url($errurl, $errurlparams));
        }
        $bbbsession['serverversion'] = (string) $serverversion;

        $typeprofiles = bigbluebutton::bigbluebuttonbn_get_instance_type_profiles();
        $enabledfeatures = config::bigbluebuttonbn_get_enabled_features($typeprofiles, $type);
        return array($bbbsession, $enabledfeatures, $typeprofiles);
    }
}