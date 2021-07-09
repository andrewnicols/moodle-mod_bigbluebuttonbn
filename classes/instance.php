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
 * Instance record for mod_bigbluebuttonbn.
 *
 * @package   mod_bigbluebuttonbn
 * @copyright 2021 Andrew Lyons <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_bigbluebuttonbn;

use cm_info;
use context_module;
use mod_bigbluebuttonbn\local\bigbluebutton;
use mod_bigbluebuttonbn\local\config;
use mod_bigbluebuttonbn\local\helpers\files;
use mod_bigbluebuttonbn\local\helpers\roles;
use moodle_url;
use stdClass;

class instance {

    /** @var cm_info The cm_info object relating to the instance */
    protected $cm;

    /** @var stdClass The course that the instance is in */
    protected $course;

    /** @var stdClass The instance data for the instance */
    protected $instancedata;

    /** @var context The current context */
    protected $context;

    /** @var array The list of participants */
    protected $participantlist;

    /** @var int The current groupid if set */
    protected $groupid;

    /** @var array Legacy data for caching */
    protected $legacydata;

    public function __construct(cm_info $cm, stdClass $course, stdClass $instancedata) {
        $this->cm = $cm;
        $this->course = $course;
        $this->instancedata = $instancedata;
    }

    /**
     * Get the instance information from an instance id.
     *
     * @param int $instanceid The id from the bigbluebuttonbn table
     * @return self
     */
    public static function get_from_instanceid(int $instanceid): self {
        global $DB;

        $coursetable = new \core\dml\table('course', 'c', 'c');
        $courseselect = $coursetable->get_field_select();
        $coursefrom = $coursetable->get_from_sql();

        $cmtable = new \core\dml\table('course_modules', 'cm', 'cm');
        $cmfrom = $cmtable->get_from_sql();

        $bbbtable = new \core\dml\table('bigbluebuttonbn', 'bbb', 'bbb');
        $bbbselect = $bbbtable->get_field_select();
        $bbbfrom = $bbbtable->get_from_sql();

        $sql = <<<EOF
    SELECT {$courseselect}, {$bbbselect}
      FROM {$cmfrom}
INNER JOIN {$coursefrom} ON c.id = cm.course
INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
INNER JOIN {$bbbfrom} ON cm.instance = bbb.id
     WHERE bbb.id = :instanceid
EOF;

        $result = $DB->get_record_sql($sql, [
            'modname' => 'bigbluebuttonbn',
            'instanceid' => $instanceid,
        ]);

        $course = $coursetable->extract_from_result($result);
        $instancedata = $bbbtable->extract_from_result($result);
        $cm = get_fast_modinfo($course)->instances['bigbluebuttonbn'][$instancedata->id];

        return new self($cm, $course, $instancedata);
    }

    /**
     * Get the instance information from a cmid.
     *
     * @param int $instanceid The id from the cmid
     * @return self
     */
    public static function get_from_cmid(int $cmid): self {
        global $DB;

        $coursetable = new \core\dml\table('course', 'c', 'c');
        $courseselect = $coursetable->get_field_select();
        $coursefrom = $coursetable->get_from_sql();

        $cmtable = new \core\dml\table('course_modules', 'cm', 'cm');
        $cmfrom = $cmtable->get_from_sql();

        $bbbtable = new \core\dml\table('bigbluebuttonbn', 'bbb', 'bbb');
        $bbbselect = $bbbtable->get_field_select();
        $bbbfrom = $bbbtable->get_from_sql();

        $sql = <<<EOF
    SELECT {$courseselect}, {$bbbselect}
      FROM {$cmfrom}
INNER JOIN {$coursefrom} ON c.id = cm.course
INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
INNER JOIN {$bbbfrom} ON cm.instance = bbb.id
     WHERE cm.id = :cmid
EOF;

        $result = $DB->get_record_sql($sql, [
            'modname' => 'bigbluebuttonbn',
            'cmid' => $cmid,
        ]);

        $course = $coursetable->extract_from_result($result);
        $instancedata = $bbbtable->extract_from_result($result);
        $cm = get_fast_modinfo($course)->get_cm($cmid);

        return new self($cm, $course, $instancedata);
    }

    /**
     * Set the current group id of the activity.
     *
     * @param int $groupid
     */
    public function set_group_id(int $groupid): void {
        $this->groupid = $groupid;
    }

    /**
     * Get the current groupid if set.
     *
     * @return null|int
     */
    public function get_group_id(): ?int {
        return $this->groupid;
    }

    /**
     * Get the group name for the current group, if a group has been set.
     *
     * @return null|string
     */
    public function get_group_name(): ?string {
        $groupid = $this->get_group_id();

        if ($groupid === null) {
            return null;
        }

        if ($groupid == 0) {
            return get_string('allparticipants');
        }

        return groups_get_group_name($groupid);
    }

    /**
     * Get the course object for the instance.
     *
     * @return stdClass
     */
    public function get_course(): stdClass {
        return $this->course;
    }

    /**
     * Get the course id of the course that the instance is in.
     *
     * @return int
     */
    public function get_course_id(): int {
        return $this->course->id;
    }

    /**
     * Get the cm_info object for the instance.
     *
     * @return cm_info
     */
    public function get_cm(): cm_info {
        return $this->cm;
    }

    /**
     * Get the context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        if ($this->context === null) {
            $this->context = context_module::instance($this->get_cm()->id);
        }

        return $this->context;
    }

    /**
     * Get the big blue button instance data.
     *
     * @return stdClass
     */
    public function get_instance_data(): stdClass {
        return $this->instancedata;
    }

    /**
     * Get the instance id.
     *
     * @return int
     */
    public function get_instance_id(): int {
        return $this->instancedata->id;
    }

    /**
     * Helper to get an instance var.
     *
     * @param string $name
     * @return string
     */
    protected function get_instance_var(string $name) {
        $instance = $this->get_instance_data();
        if (property_exists($instance, $name)) {
            return $instance->{$name};
        }

        return null;
    }

    /**
     * Get the meeting id for this meeting.
     *
     * @param null|int $groupid
     * @return string
     */
    public function get_meeting_id(?int $groupid = null): string {
        $baseid = sprintf(
            '%s-%s-%s',
            $this->get_instance_var('meetingid'),
            $this->get_course_id(),
            $this->get_instance_var('id')
        );

        if ($groupid === null) {
            $groupid = $this->get_group_id();
        }

        if ($groupid === null) {
            return $baseid;
        } else {
            return sprintf('%s[%s]', $baseid, $groupid);
        }
    }

    /**
     * Get the name of the meeting, considering any group if set.
     *
     * @return string
     */
    public function get_meeting_name(): string {
        $meetingname = $this->get_instance_var('name');

        $groupname = $this->get_group_name();
        if ($groupname === null) {
            $meetingname .= " ({$groupname})";
        }

        return $meetingname;
    }

    /**
     * Get the legacy $bbbsession data.
     *
     * Note: Anything using this function should aim to stop doing so.
     *
     * @return array
     */
    public function get_legacy_session_object(): array {
        if ($this->legacydata === null) {
            $this->legacydata = $this->generate_legacy_session_object();
        }

        return $this->legacydata;
    }

    protected function generate_legacy_session_object(): array {
        global $CFG, $USER;

        $serverversion = bigbluebutton::bigbluebuttonbn_get_server_version();
        $bbbsession = [
            'username' => fullname($USER),
            'userID' => $USER->id,

            'context' => $this->get_context(),
            'course' => $this->get_course(),
            'coursename' => $this->get_course()->fullname,
            'cm' => $this->get_cm(),
            'bigbluebuttonbn' => $this->get_instance_data(),

            'administrator' => $this->is_admin(),
            'moderator' => $this->is_moderator(),
            'managerecordings' => $this->can_manage_recordings(),
            'importrecordings' =>  $this->can_manage_recordings(),

            'modPW' => $this->get_moderator_password(),
            'viewerPW' => $this->get_viewer_password(),
            'meetingid' => $this->get_meeting_id(),
            'meetingname' => $this->get_meeting_name(),
            'meetingdescription' => $this->get_instance_var('intro'),
            'userlimit' => $this->get_user_limit(),
            'voicebridge' => $this->get_voice_bridge() ?? 0,
            'recordallfromstart' => $this->should_record_from_start(),
            'recordhidebutton' => $this->should_show_recording_button(),
            'welcome' => $this->get_welcome_message(),
            'presentation' => $this->get_presentation(),

            // Metadata.
            'bnserver' => $this->is_blindside_network_server(),
            'serverversion' => (string) $serverversion,

            // URLs.
            'bigbluebuttonbnURL' => $this->get_view_url(),
            'logoutURL' => $this->get_logout_url(),
            'recordingReadyURL' => $this->get_record_ready_url(),
            'meetingEventsURL' => $this->get_meeting_event_notification_url(),
            'joinURL' => $this->get_join_url(),
        ];

        $instancesettings = [
            'openingtime',
            'closingtime',
            'muteonstart',
            'disablecam',
            'disablemic',
            'disableprivatechat',
            'disablepublicchat',
            'disablenote',
            'hideuserlist',
            'lockedlayout',
            'lockonjoin',
            'lockonjoinconfigurable',
            'wait',
            'record',
            'welcome',
        ];
        foreach ($instancesettings as $settingname) {
            $bbbsession[$settingname] = $this->get_instance_var($settingname);
        }

        $bbbsession = array_merge(
            $bbbsession,
            (array) $this->get_origin_data()
        );

        return $bbbsession;
    }

    /**
     * Get the participant list for the session.
     *
     * @return array
     */
    public function get_participant_list(): array {
        if ($this->participantlist === null) {
            $this->participantlist = roles::bigbluebuttonbn_get_participant_list(
                $this->get_instance_data(),
                $this->get_context()
            );
        }

        return $this->participantlist;
    }

    /**
     * Whether the current user is an administrator.
     *
     * @retur bool
     */
    public function is_admin(): bool {
        global $USER;

        return is_siteadmin($USER->id);
    }

    /**
     * Whether the user is a session moderator.
     *
     * @return bool
     */
    public function is_moderator(): bool {
        return roles::bigbluebuttonbn_is_moderator(
            $this->get_context(),
            $this->get_participant_list()
        );
    }

    /**
     * Whether this user can jin the conference.
     *
     * @return bool
     */
    public function can_join(): bool {
        return has_any_capability(['moodle/category:manage', 'mod/bigbluebuttonbn:join'], $this->get_context());
    }

    /**
     * Whether this user can manage recordings.
     *
     * @return bool
     */
    public function can_manage_recordings(): bool {
        // Note: This will include site administrators.
        // The has_capability() function returns truthy for admins unless otherwise directed.
        return has_capability('mod/bigbluebuttonbn:managerecordings', $this->get_context());
    }

    /**
     * Get the configured user limit.
     *
     * @return int
     */
    public function get_user_limit(): int {
        if ((boolean) config::get('userlimit_editable')) {
           return intval($this->get_instance_var('userlimit'));
        }

        return intval((int) config::get('userlimit_default'));
    }

    /**
     * Get the voice bridge details.
     *
     * @return null|int
     */
    public function get_voice_bridge(): ?int {
        $voicebridge = (int) $this->get_instance_var('voicebridge');
        if ($voicebridge > 0) {
            return 70000 + $voicebridge;
        }

        return null;
    }

    /**
     * Get the moderator password.
     *
     * @return string
     */
    public function get_moderator_password(): string {
        return $this->get_instance_var('moderatorpass');
    }

    /**
     * Get the viewer password.
     *
     * @return string
     */
    public function get_viewer_password(): string {
        return $this->get_instance_var('viewerpass');
    }

    /**
     * Whether to show the recording button
     *
     * @return bool
     */
    public function should_show_recording_button(): bool {
        global $CFG;

        if (!empty($CFG->bigbluebuttonbn_recording_hide_button_editable)) {
            return (bool) $this->get_instance_var('recordhidebutton');
        }

        return $CFG->bigbluebuttonbn_recording_hide_button_default;
    }

    /**
     * Whether this instance is recorded.
     *
     * @return bool
     */
    public function is_recorded(): bool {
        return (bool) $this->get_instance_var('record');
    }

    /**
     * Whether this instance is recorded from the start.
     *
     * @return bool
     */
    public function should_record_from_start(): bool {
        if (!$this->is_recorded()) {
            // This meeting is not recorded.
            return false;
        }

        return (bool) $this->get_instance_var('recordallfromstart');
    }

    /**
     * Get the welcome message to display.
     *
     * @return string
     */
    public function get_welcome_message(): string {
        $welcomestring = $this->get_instance_var('welcome');
        if (empty($welcomestring)) {
            $welcomestring = get_string('mod_form_field_welcome_default', 'bigbluebuttonbn');
        }

        $welcome = [$welcomestring];

        if ($this->is_recorded()) {
            if ($this->should_record_from_start()) {
                $welcome[] = get_string('bbbrecordallfromstartwarning', 'bigbluebuttonbn');
            } else {
                $welcome[] = get_string('bbbrecordwarning', 'bigbluebuttonbn');
            }
        }

        return implode('<br><br>', $welcome);
    }

    /**
     * Get the presentation data.
     *
     * @return array
     */
    public function get_presentation(): array {
        if ($this->has_ended()) {
            return files::bigbluebuttonbn_get_presentation_array(
                $this->get_context(),
                $this->get_instance_var('presentation')
            );
        } else if ($this->is_currently_open()) {
            return files::bigbluebuttonbn_get_presentation_array(
                $this->get_context(),
                $this->get_instance_var('presentation'),
                $this->get_instance_id()
            );
        } else {
            return [];
        }
    }

    /**
     * Whether the current time is before the scheduled start time.
     *
     * @return bool
     */
    public function before_start_time(): bool {
        $openingtime = $this->get_instance_var('openingtime');
        if (empty($openingtime)) {
            return false;
        }

        return $openingtime >= time();
    }

    /**
     * Whether the meeting time has passed.
     *
     * @return bool
     */
    public function has_ended(): bool {
        $closingtime = $this->get_instance_var('closingtime');
        if (empty($closingtime)) {
            return false;
        }

        return $closingtime <= time();
    }

    /**
     * Whether this session is currently open.
     *
     * @return bool
     */
    public function is_currently_open(): bool {
        if ($this->before_start_time()) {
            return false;
        }

        if ($this->has_ended()) {
            return false;
        }

        return true;
    }

    /**
     * Get information about the origin.
     *
     * @return stdClass
     */
    public function get_origin_data(): stdClass {
        global $CFG;

        $parsedurl = parse_url($CFG->wwwroot);
        return (object) [
            'origin' => 'Moodle',
            'originVersion' => $CFG->release,
            'originServerName' => $parsedurl['host'],
            'originServerUrl' => $CFG->wwwroot,
            'originServerCommonName' => '',
            'originTag' => sprintf('moodle-mod_bigbluebuttonbn (%s)', get_config('mod_bigbluebuttonbn', 'version')),
        ];
    }

    /**
     * Whether this is a server belonging to blindside networks.
     *
     * @return bool
     */
    public function is_blindside_network_server(): bool {
        return plugin::bigbluebuttonbn_is_bn_server();
    }

    /**
     * Get the URL used to view the instance as a user.
     *
     * @return moodle_url
     */
    public function get_view_url(): moodle_url {
        return new moodle_url('/mod/bigbluebuttonbn/view.php', [
            'id' => $this->cm->id,
        ]);
    }

    /**
     * Get the logout URL used to log out of the meeting.
     *
     * @return moodle_url
     */
    public function get_logout_url(): moodle_url {
        return new moodle_url('/mod/bigbluebuttonbn/bbb_view.php', [
            'action' => 'logout',
            'id' => $this->cm->id,
        ]);
    }

    /**
     * Get the URL that the remote server will use to notify that the recording is ready.
     *
     * @return moodle_url
     */
    public function get_record_ready_url(): moodle_url {
        return new moodle_url('/mod/bigbluebuttonbn/bbb_broker.php', [
            'action' => 'recording_ready',
            'bigbluebuttonbn' => $this->instancedata->id,
        ]);
    }

    /**
     * Get the URL that the remote server will use to notify of meeting events.
     *
     * @return moodle_url
     */
    public function get_meeting_event_notification_url(): moodle_url {
        return new moodle_url('/mod/bigbluebuttonbn/bbb_broker.php', [
            'action' => 'meeting_events',
            'bigbluebuttonbn' => $this->instancedata->id,
        ]);
    }

    /**
     * Get the URL used to join a meeting.
     *
     * @return moodle_url
     */
    public function get_join_url(): moodle_url {
        return new moodle_url('/mod/bigbluebuttonbn/bbb_view.php', [
            'action' => 'join',
            'id' => $this->cm->id,
            'bn' => $this->instancedata->id,
        ]);
    }
}